import application from '../src/business/application';
import { defineConfig } from '@umijs/max';
import defaultSettings from './defaultSettings';
import proxy from './proxy';
import routes from './routes';

const { UMI_ENV = 'dev' } = process.env;

export default defineConfig({
  hash: true,
  esbuildMinifyIIFE: true,
  routes,
  npmClient: 'pnpm',
  proxy: proxy[UMI_ENV as keyof typeof proxy],
  fastRefresh: true,
  model: {},
  initialState: {},
  access: {},
  request: {},
  layout: {
    locale: false,
    ...defaultSettings,
  },
  locale: {
    default: 'zh-CN',
    antd: true,
    baseNavigator: false,
  },
  antd: {
    appConfig: {},
    configProvider: {
      theme: {
        token: {
          borderRadius: 8,
          colorPrimary: application.colorPrimary,
        },
      },
    },
  },
  title: application.title,
  ignoreMomentLocale: true,
});
