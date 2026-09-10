/** 扩展只能增加新项，不能静默覆盖框架已有的注册项。 */
export function mergeRegistrations<A extends object, B extends object>(foundation: A, business: B): A & B {
  for (const key of Object.keys(business)) {
    if (Object.prototype.hasOwnProperty.call(foundation, key) || ['__proto__', 'constructor', 'prototype'].includes(key)) {
      throw new Error(`业务注册项与框架冲突：${key}`);
    }
  }
  return { ...foundation, ...business };
}
