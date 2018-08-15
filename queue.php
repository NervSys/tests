<?php

/**
 * Nervsys test suites
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace tests;

use tests\lib\base;

use ext\redis_queue;

class queue extends base
{
    public static $tz = [
        'test_add'       => [],
        'start_root'     => [],
        'test_done'      => [],
        'test_fail'      => [],
        'test_job_100'   => [],
        'test_job_1000'  => [],
        'test_job_10000' => []
    ];

    private $queue;

    /**
     * queue constructor.
     */
    public function __construct()
    {
        $this->queue = redis_queue::new();
    }

    /**
     * Queue test process
     *
     * @param string $rand
     * @param bool   $bool
     *
     * @return bool
     */
    public function process(string $rand, bool $bool): bool
    {
        unset($rand);
        return $bool;
    }

    /**
     * Test add 1 job
     */
    public function test_add(): void
    {
        $left = $this->queue->add(
            'test', 'tests/queue-process',
            [
                'rand' => hash('sha256', uniqid(mt_rand(), true)),
                'bool' => true
            ]
        );

        $remain = $this->queue->add(
            'test', 'tests/queue-process',
            [
                'rand' => hash('sha256', uniqid(mt_rand(), true)),
                'bool' => true
            ]
        );

        self::chk_eq('add 1 job', [$remain - $left, 1]);
    }

    /**
     * Start root process
     */
    public function start_root(): void
    {
        \ext\mpc::new(1, false)->add('ext/redis_queue-root')->commit();
    }

    /**
     * Test done init jobs
     */
    public function test_done(): void
    {
        self::chk_eq('init jobs done', [$this->chk_job(), 0]);
    }

    /**
     * Test add 1 fail job
     */
    public function test_fail(): void
    {
        $left = $this->queue->show_fail(0, 1);

        $this->queue->add(
            'test' . mt_rand(1, 10), 'tests/queue-process',
            [
                'rand' => hash('sha256', uniqid(mt_rand(), true)),
                'bool' => false
            ]
        );

        while (0 < $this->chk_job()) ;

        $remain = $this->queue->show_fail(0, 1);

        self::chk_eq('add 1 fail job', [$remain['len'] - $left['len'], 1]);
        echo PHP_EOL;
    }

    /**
     * Test 100 jobs
     *
     * @param int $jobs
     */
    public function test_job_100(int $jobs = 100): void
    {
        for ($i = 0; $i < $jobs; ++$i) {
            $this->queue->add(
                'test' . mt_rand(1, 10), 'tests/queue-process',
                [
                    'rand' => hash('sha256', uniqid(mt_rand(), true)),
                    'bool' => true
                ]
            );
        }

        $time = microtime(true);

        self::chk_eq($jobs . ' jobs done', [$this->chk_job(), 0]);
        echo 'Time Taken: ' . round(microtime(true) - $time, 4) . 's';
        echo PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Test 1000 jobs
     *
     * @param int $jobs
     */
    public function test_job_1000(int $jobs = 1000): void
    {
        for ($i = 0; $i < $jobs; ++$i) {
            $this->queue->add(
                'test' . mt_rand(1, 10), 'tests/queue-process',
                [
                    'rand' => hash('sha256', uniqid(mt_rand(), true)),
                    'bool' => true
                ]
            );
        }

        $time = microtime(true);

        self::chk_eq($jobs . ' jobs done', [$this->chk_job(), 0]);
        echo 'Time Taken: ' . round(microtime(true) - $time, 4) . 's';
        echo PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Test 10000 jobs
     *
     * @param int $jobs
     */
    public function test_job_10000(int $jobs = 10000): void
    {
        for ($i = 0; $i < $jobs; ++$i) {
            $this->queue->add(
                'test' . mt_rand(1, 10), 'tests/queue-process',
                [
                    'rand' => hash('sha256', uniqid(mt_rand(), true)),
                    'bool' => true
                ]
            );
        }

        $time = microtime(true);

        self::chk_eq($jobs . ' jobs done', [$this->chk_job(), 0]);
        echo 'Time Taken: ' . round(microtime(true) - $time, 4) . 's';
        echo PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * @return int
     */
    private function chk_job(): int
    {
        $redis = $this->queue->connect();

        do {
            //Jobs
            $jobs = 0;

            //Read queue list
            $queue = $this->queue->show_queue();

            //Count jobs
            foreach ($queue as $key => $item) {
                $jobs += $redis->lLen($key);
            }

            if (0 === $jobs) {
                return 0;
            }

            sleep(ceil(log1p($jobs + 1)));

            //Left jobs
            $left = 0;

            //Read queue list
            $queue = $this->queue->show_queue();

            //Count left jobs
            foreach ($queue as $key => $item) {
                $left += $redis->lLen($key);
            }
        } while (0 < $jobs && 0 < $left && $left < $jobs);

        return $left < $jobs ? $left : $jobs;
    }
}