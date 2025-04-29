import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { Poster } from './entities/poster.entity';
import { PostersService } from './posters.service';
import { PostersController } from './posters.controller';

@Module({
  imports: [TypeOrmModule.forFeature([Poster])],
  controllers: [PostersController],
  providers: [PostersService],
  exports: [PostersService],
})
export class PostersModule {}
