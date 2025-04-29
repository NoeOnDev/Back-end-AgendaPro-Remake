import { CacheModuleOptions } from '@nestjs/cache-manager';
import { ConfigService } from '@nestjs/config';
import { createKeyv } from '@keyv/redis';

export const cacheConfig = (
  configService: ConfigService,
): CacheModuleOptions => {
  const isProduction = configService.get('NODE_ENV') === 'production';

  // En producción usa Redis, en desarrollo usa memoria
  if (isProduction) {
    return {
      stores: [
        createKeyv(
          `redis://${configService.get('REDIS_HOST', 'localhost')}:${configService.get('REDIS_PORT', 6379)}`,
        ),
      ],
      ttl: +configService.get('CACHE_TTL', 60000),
    };
  } else {
    return {
      ttl: +configService.get('CACHE_TTL', 60000),
      max: 100,
    };
  }
};
