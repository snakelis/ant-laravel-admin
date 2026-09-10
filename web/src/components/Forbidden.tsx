import { history } from '@umijs/max';
import { Button, Result } from 'antd';

export const Forbidden = () => (
  <Result
    status="403"
    title="无权限"
    subTitle="当前账号没有访问此页面所需的权限码。前端隐藏只用于改善体验，实际授权以服务端校验为准。"
    extra={<Button onClick={() => history.push('/')}>返回首页</Button>}
  />
);
