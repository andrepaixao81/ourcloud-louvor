<?php
namespace Tests\Unit;
use App\Services\SongTitleNormalizer;
use PHPUnit\Framework\TestCase;
class SongTitleNormalizerTest extends TestCase
{
    public function test_it_normalizes_accents_case_and_spacing(): void { $this->assertSame('aguas purificadoras', (new SongTitleNormalizer())->normalize('  Águas   Purificadoras  ')); }
}
