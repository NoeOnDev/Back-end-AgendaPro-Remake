import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Poster } from './entities/poster.entity';
import { CreatePosterDto } from './dto/create-poster.dto';
import { UpdatePosterDto } from './dto/update-poster.dto';

@Injectable()
export class PostersService {
  constructor(
    @InjectRepository(Poster)
    private postersRepository: Repository<Poster>,
  ) {}

  create(createPosterDto: CreatePosterDto): Promise<Poster> {
    const poster = this.postersRepository.create(createPosterDto);
    return this.postersRepository.save(poster);
  }

  findAll(): Promise<Poster[]> {
    return this.postersRepository.find();
  }

  async findOne(id: number): Promise<Poster> {
    const poster = await this.postersRepository.findOneBy({ id });
    if (!poster) {
      throw new NotFoundException(`Poster con ID ${id} no encontrado`);
    }
    return poster;
  }

  async update(id: number, updatePosterDto: UpdatePosterDto): Promise<Poster> {
    const poster = await this.findOne(id);
    this.postersRepository.merge(poster, updatePosterDto);
    return this.postersRepository.save(poster);
  }

  async remove(id: number): Promise<void> {
    const poster = await this.findOne(id);
    await this.postersRepository.remove(poster);
  }
}
