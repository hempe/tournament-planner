import config from '../../ngsw-config.json';

describe('ngsw-config.json', () => {
  it('never routes /api to the app shell', () => {
    expect(config.navigationUrls).toContain('!/api/**');
  });

  it('does not cache API responses', () => {
    expect('dataGroups' in config).toBe(false);
    const patterns = config.assetGroups.flatMap((group) => group.resources.files);
    expect(patterns.filter((pattern) => pattern.includes('/api'))).toEqual([]);
    expect(patterns.some((pattern) => pattern === '/**' || pattern === '/*')).toBe(false);
  });
});
