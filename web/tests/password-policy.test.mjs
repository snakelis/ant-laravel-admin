import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import ts from 'typescript';
const module = { exports: {} };
runInNewContext(ts.transpileModule(readFileSync(new URL('../src/utils/passwordPolicy.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText, { module, exports: module.exports });
const validate = module.exports.passwordPolicyError;
const strong = { min_length: 12, max_length: 255, mixed_case: true, numbers: true, symbols: true };
test('默认规则检查长度与字符组合', () => {
  for (const value of ['short', 'longlowercase1!', 'LongPassword!!', 'LongPassword12']) assert(validate(value, strong));
  assert.equal(validate('LongPassword12!', strong), undefined);
});
test('业务规则放宽后使用同一校验器，Unicode 按字符计数', () => {
  const simple = { ...strong, min_length: 5, mixed_case: false, numbers: false, symbols: false };
  assert.equal(validate('plain', simple), undefined);
  assert(validate('tiny', simple));
  assert(validate('😀😀😀😀', simple));
  assert.equal(validate('😀😀😀😀😀', simple), undefined);
  assert(validate('a'.repeat(256), simple));
});
