import { Controller, Get } from '@nestjs/common';
import { BenchService } from './bench.service';

@Controller()
export class BenchController {
    constructor(private readonly service: BenchService) {}

    @Get('debug/runtime')
    runtime() {
        return { version: process.version, versions: process.versions, pool_max: 10 };
    }

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

    @Get('bench-async')
    benchAsync() {
        return this.service.bench(true);
    }

    @Get('debug/connections')
    connections() {
        return this.service.connections();
    }
}
