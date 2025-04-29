import { PartialType } from '@nestjs/mapped-types';
import { CreatePosterDto } from './create-poster.dto';

export class UpdatePosterDto extends PartialType(CreatePosterDto) {}
