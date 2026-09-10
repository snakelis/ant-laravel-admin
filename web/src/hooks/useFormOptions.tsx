import { Alert, Button } from 'antd';
import { useCallback, useEffect, useState } from 'react';

export function useFormOptions<T>(loader: () => Promise<T>, open: boolean) {
  const [data, setData] = useState<T>();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [version, setVersion] = useState(0);
  const retry = useCallback(() => setVersion((value) => value + 1), []);
  useEffect(() => {
    if (!open) return;
    let active = true;
    setLoading(true);
    setError(false);
    void loader()
      .then((result) => {
        if (active) setData(result);
      })
      .catch(() => {
        if (active) setError(true);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [loader, open, version]);
  const notice = error ? (
    <Alert
      type="error"
      showIcon
      title="选项加载失败，暂时无法保存"
      action={
        <Button size="small" onClick={retry}>
          重试
        </Button>
      }
      style={{ marginBottom: 16 }}
    />
  ) : null;
  return {
    data,
    loading,
    ready: Boolean(data) && !loading && !error,
    notice,
    retry,
  };
}
