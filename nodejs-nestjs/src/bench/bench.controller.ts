import { Controller, Get } from '@nestjs/common';
import { BenchService } from './bench.service';

@Controller()
export class BenchController {
    constructor(private readonly service: BenchService) {}

    @Get('hello')
    hello() {
        return this.service.hello();
    }

    @Get('test')
    test() {
        return this.service.test();
    }

    @Get('bench')
    bench() {
        return this.service.bench();
    }

    @Get('debug/connections')
    connections() {
        return this.service.connections();
    }
}
