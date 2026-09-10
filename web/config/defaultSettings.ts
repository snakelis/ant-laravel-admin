import application from '../src/business/application';
import type { ProLayoutProps } from '@ant-design/pro-components';

const Settings: ProLayoutProps & { logo?: string | false } = {
  navTheme: 'light',
  colorPrimary: application.colorPrimary,
  layout: 'mix',
  contentWidth: 'Fluid',
  fixedHeader: false,
  fixSiderbar: true,
  colorWeak: false,
  title: application.title,
  logo: application.logo,
  iconfontUrl: '',
};

export default Settings;
