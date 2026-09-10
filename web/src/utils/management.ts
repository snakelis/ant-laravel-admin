import type { MenuItem, PermissionTreeNode } from '../types';

export function filterMenus(menus: MenuItem[], keyword: string): MenuItem[] {
  const term = keyword.trim().toLowerCase();
  return menus.flatMap((menu) => {
    const matches = [
      menu.title,
      menu.name,
      menu.path,
      menu.permission_name,
    ].some((value) => value?.toLowerCase().includes(term));
    const children = matches
      ? menu.children
      : filterMenus(menu.children ?? [], term);
    return matches || children?.length
      ? [{ ...menu, children: children?.length ? children : undefined }]
      : [];
  });
}

export function parentMenuOptions(
  menus: MenuItem[],
  excludedId?: number,
): {
  title: string;
  value: number;
  children?: ReturnType<typeof parentMenuOptions>;
}[] {
  return menus
    .filter((menu) => menu.id !== excludedId)
    .map((menu) => ({
      title: menu.title,
      value: menu.id,
      children: parentMenuOptions(menu.children ?? [], excludedId),
    }));
}

export function filterPermissionTree(
  nodes: PermissionTreeNode[],
  keyword: string,
  selected?: ReadonlySet<number>,
  ancestorMatches = false,
): PermissionTreeNode[] {
  const term = keyword.trim().toLowerCase();
  return nodes.flatMap((node) => {
    const matches =
      ancestorMatches ||
      [node.title, node.name, node.action_title].some((value) =>
        value?.toLowerCase().includes(term),
      );
    if (node.type === 'permission')
      return matches && (!selected || selected.has(node.id!)) ? [node] : [];
    const children = filterPermissionTree(
      node.children ?? [],
      term,
      selected,
      matches,
    );
    return children.length ? [{ ...node, children }] : [];
  });
}

export function mergeVisibleSelection(
  current: number[],
  visibleEditable: number[],
  next: number[],
): number[] {
  const visible = new Set(visibleEditable);
  return [...new Set([...current.filter((id) => !visible.has(id)), ...next])];
}
