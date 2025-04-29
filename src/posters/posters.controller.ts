import {
  Controller,
  Get,
  Post,
  Body,
  Patch,
  Param,
  Delete,
  ParseIntPipe,
} from '@nestjs/common';
import { PostersService } from './posters.service';
import { CreatePosterDto } from './dto/create-poster.dto';
import { UpdatePosterDto } from './dto/update-poster.dto';
import { Poster } from './entities/poster.entity';

@Controller('posters')
export class PostersController {
  constructor(private readonly postersService: PostersService) {}

  @Post()
  create(@Body() createPosterDto: CreatePosterDto): Promise<Poster> {
    return this.postersService.create(createPosterDto);
  }

  @Get()
  findAll(): Promise<Poster[]> {
    return this.postersService.findAll();
  }

  @Get(':id')
  findOne(@Param('id', ParseIntPipe) id: number): Promise<Poster> {
    return this.postersService.findOne(id);
  }

  @Patch(':id')
  update(
    @Param('id', ParseIntPipe) id: number,
    @Body() updatePosterDto: UpdatePosterDto,
  ): Promise<Poster> {
    return this.postersService.update(id, updatePosterDto);
  }

  @Delete(':id')
  remove(@Param('id', ParseIntPipe) id: number): Promise<void> {
    return this.postersService.remove(id);
  }
}
