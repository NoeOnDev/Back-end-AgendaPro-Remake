import { Injectable, NotFoundException, Inject } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Poster } from './entities/poster.entity';
import { CreatePosterDto } from './dto/create-poster.dto';
import { UpdatePosterDto } from './dto/update-poster.dto';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import { Cache } from 'cache-manager';

@Injectable()
export class PostersService {
  constructor(
    @InjectRepository(Poster)
    private postersRepository: Repository<Poster>,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
  ) {}

  create(createPosterDto: CreatePosterDto): Promise<Poster> {
    const poster = this.postersRepository.create(createPosterDto);
    return this.postersRepository.save(poster);
  }

  async findAll(): Promise<Poster[]> {
    const cachedPosters = await this.cacheManager.get<Poster[]>('all_posters');
    if (cachedPosters) {
      return cachedPosters;
    }

    const posters = await this.postersRepository.find();

    await this.cacheManager.set('all_posters', posters);

    return posters;
  }

  async findOne(id: number): Promise<Poster> {
    const cacheKey = `poster_${id}`;
    const cachedPoster = await this.cacheManager.get<Poster>(cacheKey);
    if (cachedPoster) {
      return cachedPoster;
    }

    const poster = await this.postersRepository.findOneBy({ id });
    if (!poster) {
      throw new NotFoundException(`Poster con ID ${id} no encontrado`);
    }

    await this.cacheManager.set(cacheKey, poster);

    return poster;
  }

  async update(id: number, updatePosterDto: UpdatePosterDto): Promise<Poster> {
    const poster = await this.findOne(id);
    this.postersRepository.merge(poster, updatePosterDto);

    const updatedPoster = await this.postersRepository.save(poster);

    await this.cacheManager.del(`poster_${id}`);
    await this.cacheManager.del('all_posters');

    return updatedPoster;
  }

  async remove(id: number): Promise<void> {
    const poster = await this.findOne(id);
    await this.postersRepository.remove(poster);

    await this.cacheManager.del(`poster_${id}`);
    await this.cacheManager.del('all_posters');
  }
}
