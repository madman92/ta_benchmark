import { Module } from '@nestjs/common';
import { PgModule } from './pg/pg.module';
import { BenchController } from './bench/bench.controller';
import { BenchService } from './bench/bench.service';

@Module({
    imports: [PgModule],
    controllers: [BenchController],
    providers: [BenchService],
})
export class AppModule {}
