const target = process.env.UMI_APP_DEV_API_TARGET || 'http://127.0.0.1:8000';

export default {
  dev: {
    '/api/': {
      target,
      changeOrigin: true,
    },
    '/sanctum/': {
      target,
      changeOrigin: true,
    },
  },
};
