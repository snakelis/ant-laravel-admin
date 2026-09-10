import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import ts from 'typescript';

// 使用已有 TypeScript 编译器执行真实服务模块，仅替换浏览器和 HTTP 边界。
const sourceRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../src');
function harness(fetchImplementation) {
  const modules = new Map();
  const redirects = [];
  const timers = new Set();
  const history = {
    location: { pathname: '/system/users', search: '?page=2', hash: '' },
    replace: (path) => redirects.push(path),
  };
  function load(path) {
    if (modules.has(path)) return modules.get(path).exports;
    const module = { exports: {} };
    modules.set(path, module);
    const output = ts.transpileModule(readFileSync(path, 'utf8'), {
      compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    runInNewContext(output, {
      module, exports: module.exports,
      require: (name) => {
        if (name === '@umijs/max') return { history };
        if (!name.startsWith('.')) throw new Error(`Unexpected import: ${name}`);
        return load(resolve(dirname(path), `${name}.ts`));
      },
      process: { env: {} }, URL, AbortController, Error,
      window: { location: { origin: 'http://localhost:5173' } },
      document: { cookie: 'XSRF-TOKEN=test-csrf-value' },
      fetch: fetchImplementation,
      setTimeout: (callback, delay) => {
        assert.equal(delay, 10000);
        timers.add(callback);
        return callback;
      },
      clearTimeout: (callback) => timers.delete(callback),
    }, { filename: path });
    return module.exports;
  }
  return {
    session: load(resolve(sourceRoot, 'services/session.ts')),
    api: load(resolve(sourceRoot, 'services/api.ts')),
    request: load(resolve(sourceRoot, 'services/request.ts')),
    redirects, timers,
  };
}

const response = (status, data = null) => new Response(JSON.stringify({
  success: status < 400, code: status < 400 ? 'ok' : 'test_error', message: 'Test response', data,
}), { status });

test('加载成功返回当前用户，401 返回未登录且不重复跳转', async () => {
  const profile = { user: { id: 1 }, roles: [], permissions: [], menus: [] };
  const success = harness(async () => response(200, profile));
  assert.equal(JSON.stringify(await success.session.fetchCurrentUser()), JSON.stringify(profile));
  const expired = harness(async () => response(401));
  assert.equal(await expired.session.fetchCurrentUser(), undefined);
  assert.equal(expired.redirects.length, 0);
  assert.equal(expired.timers.size, 0);
});

test('加载会话时的 403、419、429、500、网络故障不会伪装成未登录', async () => {
  for (const status of [403, 419, 429, 500]) {
    const app = harness(async () => response(status));
    await assert.rejects(app.session.fetchCurrentUser(), (error) => error.status === status);
    assert.equal(app.redirects.length, 0);
  }
  const app = harness(async () => { throw new TypeError('offline'); });
  await assert.rejects(app.session.fetchCurrentUser(), (error) => error.code === 'network_error');
});

test('退出成功或会话已过期可以完成退出', async () => {
  for (const status of [200, 401]) {
    const app = harness(async () => response(status));
    await app.session.endSession();
    assert.equal(app.timers.size, 0);
  }
});

test('退出遇到 419、500 或断网必须失败，不自动重放退出请求', async () => {
  for (const status of [419, 500, 0]) {
    let requests = 0;
    const app = harness(async () => {
      requests++;
      if (!status) throw new TypeError('offline');
      return response(status);
    });
    await assert.rejects(app.session.endSession());
    assert.equal(requests, 1);
    assert.equal(app.redirects.length, 0);
  }
});

test('超时会中止请求并向会话调用方报告失败', async () => {
  const app = harness((_url, init) => new Promise((_resolve, reject) => {
    init.signal.addEventListener('abort', () => reject(new Error('aborted')));
  }));
  const pending = app.session.endSession();
  const check = assert.rejects(pending, (error) => error.code === 'request_timeout');
  for (const expire of app.timers) expire();
  await check;
  assert.equal(app.timers.size, 0);
});

test('调用方取消请求不会被当成超时', async () => {
  const app = harness((_url, init) => new Promise((_resolve, reject) => {
    init.signal.addEventListener('abort', () => reject(new Error('aborted')));
  }));
  const controller = new AbortController();
  const pending = app.request.request('/me', { signal: controller.signal });
  const check = assert.rejects(pending, (error) => error.code === 'request_cancelled');
  controller.abort();
  await check;
  assert.equal(app.timers.size, 0);
});

test('登录先获取 CSRF Cookie，再带凭据和 CSRF 头提交', async () => {
  const calls = [];
  const app = harness(async (url, init) => {
    calls.push({ url, init });
    return url.endsWith('/sanctum/csrf-cookie') ? new Response(null, { status: 204 }) : response(200, {});
  });
  await app.api.authApi.login({ email: 'test@example.com', password: 'test-only' });
  assert.equal(calls.length, 2);
  assert.ok(calls[0].url.endsWith('/sanctum/csrf-cookie'));
  assert.equal(calls[0].init.credentials, 'include');
  assert.equal(calls[1].init.method, 'POST');
  assert.equal(calls[1].init.credentials, 'include');
  assert.equal(calls[1].init.headers['X-XSRF-TOKEN'], 'test-csrf-value');
});

test('普通 API 的 401 保留当前路径跳转登录，403 不跳转', async () => {
  const expired = harness(async () => response(401));
  await assert.rejects(expired.request.request('/users'));
  assert.equal(expired.redirects[0], '/login?redirect=%2Fsystem%2Fusers%3Fpage%3D2');
  const forbidden = harness(async () => response(403));
  await assert.rejects(forbidden.request.request('/users'));
  assert.equal(forbidden.redirects.length, 0);
});
