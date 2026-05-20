<?php

namespace Tests\Feature\Unit;

use Tests\TestCase;

class FilesystemTmpForTestsDiskConfigTest extends TestCase
{
    public function test_tmp_for_tests_disk_is_configured_with_local_driver(): void
    {
        $this->assertSame('local', config('filesystems.disks.tmp-for-tests.driver'));
        $this->assertNotEmpty(config('filesystems.disks.tmp-for-tests.root'));
    }
}
