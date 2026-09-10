import { Card, Space, Typography } from 'antd';
import { registeredComponentNames } from '../layouts/componentRegistry';

export const SystemOverviewPage = () => (
  <Card>
    <Space direction="vertical" size="middle">
      <Typography.Title level={4}>后台管理基础框架</Typography.Title>
      <Typography.Paragraph>
        当前前端基于 Ant Design Pro v6.0.2 / Umi Max 官方工程体系，后端菜单仅决定导航展示和可见性。
      </Typography.Paragraph>
      <Typography.Paragraph>
        后端组件标识只会匹配受控组件注册表，不执行后端返回的任意路径、动态 import 或动态代码。
      </Typography.Paragraph>
      <Typography.Paragraph>已注册组件：{registeredComponentNames.join('、')}</Typography.Paragraph>
      <Typography.Paragraph type="secondary">
        前端菜单和按钮隐藏仅用于改善用户体验，接口访问权限始终由 Laravel Sanctum、can 中间件和 Policy 校验。
      </Typography.Paragraph>
    </Space>
  </Card>
);

export default SystemOverviewPage;
