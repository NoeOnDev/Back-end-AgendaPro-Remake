import { IsNotEmpty, IsString, IsBoolean, IsOptional } from 'class-validator';

export class CreatePosterDto {
  @IsNotEmpty()
  @IsString()
  title: string;

  @IsNotEmpty()
  @IsString()
  description: string;

  @IsOptional()
  @IsBoolean()
  isPublic?: boolean;
}
