import type { PasswordPolicy } from '../types';

export function passwordPolicyError(value: string, policy: PasswordPolicy): string | undefined {
  const length = Array.from(value).length;
  if (length < policy.min_length) return `至少 ${policy.min_length} 位`;
  if (length > policy.max_length) return `最多 ${policy.max_length} 位`;
  if (policy.mixed_case && !/(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u.test(value)) return '需包含大小写字母';
  if (policy.numbers && !/\p{N}/u.test(value)) return '需包含数字';
  if (policy.symbols && !/[\p{Z}\p{S}\p{P}]/u.test(value)) return '需包含符号';
  return undefined;
}
