import { Navigate, useModel } from '@umijs/max';
import { firstNavigablePath } from '../layouts/menu';

const RootRedirect = () => {
  const { initialState } = useModel('@@initialState');
  const path = firstNavigablePath(initialState?.menus ?? [], new Set(initialState?.permissions ?? []));

  return <Navigate replace to={path} />;
};

export default RootRedirect;
