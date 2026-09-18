<?php

use App\Libraries\ContextBuilder;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Verifikasi perbaikan B1 (duplikasi & urutan pesan) dan B2 (riwayat tanpa batas).
 *
 * Algoritma ini sebelumnya juga diverifikasi lewat simulasi Python terhadap
 * skenario 400 pesan; test ini memastikan implementasi PHP-nya setara.
 */
final class ContextBuilderTest extends CIUnitTestCase
{
    private const SYSTEM = "[ATURAN WAJIB]\n1. Jawab hanya dari data.\n\n[DATA AKADEMIK]\nNPM 202110110 | MK01 SKS 3 Nilai A\n";

    /** @return array<int, array{role:string, content:string}> */
    private function history(int $pairs, int $padChars = 100): array
    {
        $h = [];
        for ($i = 1; $i <= $pairs; $i++) {
            $h[] = ['role' => 'user',      'content' => "Pertanyaan nomor {$i} " . str_repeat('a', $padChars)];
            $h[] = ['role' => 'assistant', 'content' => "Jawaban nomor {$i} " . str_repeat('b', $padChars)];
        }

        return $h;
    }

    // ==================================================== B1 · struktur payload

    public function testPesanSaatIniMunculTepatSatuKaliDiPosisiTerakhir(): void
    {
        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $this->history(3), 'berapa SKS saya?');

        $matches = array_filter($ctx, static fn ($m) => $m['content'] === 'berapa SKS saya?');

        $this->assertCount(1, $matches, 'pesan user tidak boleh terkirim dua kali');
        $this->assertSame('user', end($ctx)['role'], 'pesan terakhir harus dari user');
        $this->assertSame('berapa SKS saya?', end($ctx)['content']);
    }

    public function testSystemSelaluDiPosisiPertama(): void
    {
        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $this->history(3), 'halo');

        $this->assertSame('system', $ctx[0]['role']);
        $this->assertStringContainsString('ATURAN WAJIB', $ctx[0]['content']);
    }

    public function testRiwayatBerselangSelingDanDiawaliUser(): void
    {
        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $this->history(4), 'lanjut');

        $body = array_slice($ctx, 1, -1);   // tanpa system dan tanpa pesan terakhir
        $this->assertNotEmpty($body);
        $this->assertSame('user', $body[0]['role'], 'riwayat harus diawali turn user');

        foreach ($body as $i => $m) {
            $expected = $i % 2 === 0 ? 'user' : 'assistant';
            $this->assertSame($expected, $m['role'], "posisi {$i} harus selang-seling");
        }
    }

    /**
     * Bug lama: pesan disimpan ke DB sebelum konteks dirakit, sehingga riwayat
     * sudah memuat pesan saat ini. ContextBuilder harus tetap menduplikasinya.
     */
    public function testDedupeBilaPesanSaatIniSudahAdaDiRiwayat(): void
    {
        $current = 'berapa SKS saya?';
        $history = $this->history(2);
        $history[] = ['role' => 'user', 'content' => $current];   // sudah tersimpan

        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $history, $current);

        $n = count(array_filter($ctx, static fn ($m) => $m['content'] === $current));
        $this->assertSame(1, $n, 'dedupe harus mencegah pesan ganda');
    }

    public function testBarisSystemDanPesanKosongDibuang(): void
    {
        $history = [
            ['role' => 'system',    'content' => 'Session started'],
            ['role' => 'user',      'content' => 'halo'],
            ['role' => 'assistant', 'content' => ''],           // kosong
            ['role' => 'assistant', 'content' => 'hai'],
            ['role' => 'user',      'content' => '   '],        // spasi saja
        ];

        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $history, 'lanjut');

        $roles = array_map(static fn ($m) => $m['role'], $ctx);
        $this->assertSame(['system', 'user', 'assistant', 'user'], $roles);
        $this->assertNotContains('Session started', array_column($ctx, 'content'));
    }

    // ==================================================== B2 · anggaran konteks

    public function testPromptTidakPernahMelebihiAnggaran(): void
    {
        $budget  = 3000;
        $history = $this->history(300, 400);      // jauh melebihi anggaran

        $ctx   = (new ContextBuilder($budget))->build(self::SYSTEM, $history, 'berapa SKS?');
        $total = array_sum(array_map(
            static fn ($m) => ContextBuilder::estimateTokens($m['content']),
            $ctx
        ));

        // Toleransi kecil untuk teks catatan yang ditempel di akhir
        $this->assertLessThanOrEqual($budget + 60, $total, "total {$total} token melewati anggaran {$budget}");
    }

    /**
     * Inti perbaikan B2: system prompt harus SELALU bertahan. Sebelumnya,
     * riwayat tanpa batas membuat provider memotong dari depan dan membuang
     * system prompt beserta seluruh data akademik.
     */
    public function testSystemPromptSelamatMeskiRiwayatSangatPanjang(): void
    {
        $ctx = (new ContextBuilder(2500))->build(self::SYSTEM, $this->history(500, 500), 'berapa SKS?');

        $this->assertSame('system', $ctx[0]['role']);
        $this->assertStringContainsString('ATURAN WAJIB', $ctx[0]['content'], 'aturan grounding harus utuh');
        $this->assertStringContainsString('DATA AKADEMIK', $ctx[0]['content'], 'data akademik harus utuh');
    }

    public function testRiwayatTerpangkasDariYangTerlama(): void
    {
        $history = $this->history(50, 200);
        $ctx     = (new ContextBuilder(1500))->build(self::SYSTEM, $history, 'lanjut');

        $contents = array_column($ctx, 'content');

        // Pesan terbaru harus bertahan
        $this->assertContains('Pertanyaan nomor 50 ' . str_repeat('a', 200), $contents);
        // Pesan tertua harus sudah dibuang
        $this->assertNotContains('Pertanyaan nomor 1 ' . str_repeat('a', 200), $contents);
    }

    public function testSesiPendekTidakKehilanganApaPun(): void
    {
        $history = [
            ['role' => 'user',      'content' => 'halo'],
            ['role' => 'assistant', 'content' => 'halo juga'],
        ];

        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $history, 'lanjut');

        $this->assertCount(4, $ctx, 'system + 2 riwayat + pesan baru');
        $this->assertSame('halo', $ctx[1]['content']);
        $this->assertSame('halo juga', $ctx[2]['content']);
    }

    public function testCatatanKonteksDiberitahukanSaatAdaYangDipangkas(): void
    {
        $ctx = (new ContextBuilder(800))->build(self::SYSTEM, $this->history(100, 300), 'lanjut');

        $this->assertStringContainsString('[CATATAN KONTEKS]', $ctx[0]['content']);
        $this->assertStringContainsString('JANGAN mengarang', $ctx[0]['content']);
    }

    public function testTidakAdaCatatanBilaSemuaMuat(): void
    {
        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $this->history(2), 'halo');

        $this->assertStringNotContainsString('[CATATAN KONTEKS]', $ctx[0]['content']);
    }

    // ============================================== system prompt kebesaran

    public function testSystemRaksasaDipotongTapiAturanTetapSelamat(): void
    {
        $huge = "[ATURAN WAJIB]\nJangan mengarang.\n\n[DATA AKADEMIK]\n" . str_repeat('X', 60000);

        $ctx   = (new ContextBuilder(2000))->build($huge, $this->history(2), 'halo');
        $total = array_sum(array_map(
            static fn ($m) => ContextBuilder::estimateTokens($m['content']),
            $ctx
        ));

        $this->assertLessThanOrEqual(2100, $total);
        $this->assertStringContainsString('ATURAN WAJIB', $ctx[0]['content']);
        $this->assertStringContainsString('Jangan mengarang.', $ctx[0]['content'], 'isi aturan harus utuh');
        $this->assertStringContainsString('[CATATAN]', $ctx[0]['content'], 'harus ada penanda pemotongan');
    }

    public function testPemotonganSystemTetapMemuatSebagianData(): void
    {
        // Jangan buang seluruh data: sisa anggaran dipakai memuat sebanyak mungkin
        $huge = "[ATURAN]\nX\n\n[DATA AKADEMIK]\n" . str_repeat('DATA', 20000);

        $ctx = (new ContextBuilder(3000))->build($huge, [], 'halo');

        $this->assertStringContainsString('DATA', $ctx[0]['content']);
        $this->assertGreaterThan(
            100,
            mb_strlen($ctx[0]['content']),
            'sebagian data seharusnya masih termuat, bukan dibuang seluruhnya'
        );
    }

    // ==================================================== utilitas

    public function testEstimateTokensKonsisten(): void
    {
        $this->assertSame(1, ContextBuilder::estimateTokens(''));
        $this->assertSame(1, ContextBuilder::estimateTokens('abc'));
        $this->assertSame(3, ContextBuilder::estimateTokens(str_repeat('a', 10)));   // ceil(10/3.6)
        $this->assertSame(278, ContextBuilder::estimateTokens(str_repeat('a', 1000)));
    }

    public function testAnggaranSangatKecilTidakMeledak(): void
    {
        // Konstruktor menaikkan batas bawah; harus tetap menghasilkan payload sah
        $ctx = (new ContextBuilder(1, 1))->build(self::SYSTEM, $this->history(5, 500), 'halo');

        $this->assertSame('system', $ctx[0]['role']);
        $this->assertSame('user', end($ctx)['role']);
        $this->assertSame('halo', end($ctx)['content'], 'pesan saat ini harus selalu utuh');
    }

    public function testRiwayatKosongMenghasilkanSystemPlusUser(): void
    {
        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, [], 'halo');

        $this->assertCount(2, $ctx);
        $this->assertSame('system', $ctx[0]['role']);
        $this->assertSame('user', $ctx[1]['role']);
    }

    public function testRiwayatBerisiArrayRusakTidakMeledak(): void
    {
        $history = [
            'bukan array',
            ['role' => 'user'],                       // tanpa content
            ['content' => 'tanpa role'],
            ['role' => 'user', 'content' => 'sah'],
        ];

        $ctx = (new ContextBuilder(6000))->build(self::SYSTEM, $history, 'lanjut');

        $this->assertContains('sah', array_column($ctx, 'content'));
        $this->assertSame('user', end($ctx)['role']);
    }

    public function testSanitizeHistoryDapatDipakaiTerpisah(): void
    {
        $out = ContextBuilder::sanitizeHistory(
            [['role' => 'system', 'content' => 'x'], ['role' => 'user', 'content' => 'y']],
            'z'
        );

        $this->assertCount(1, $out);
        $this->assertSame(['role' => 'user', 'content' => 'y'], $out[0]);
    }
}
