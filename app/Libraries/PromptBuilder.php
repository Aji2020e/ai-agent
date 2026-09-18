<?php

namespace App\Libraries;

use App\Libraries\Skills\SkillResult;

/**
 * Pembangun SYSTEM PROMPT.
 *
 * PERUBAHAN SIGNATURE: kelas ini sekarang hanya mengembalikan *string system*,
 * bukan lagi array [system, user]. Menyisipkan pesan user di sini adalah
 * penyebab pesan terkirim dua kali dan dalam urutan salah — tugas merakit
 * urutan kini ada di ContextBuilder.
 *
 * Fokus kelas ini: membuat model menjawab BERDASARKAN DATA, bukan ingatan.
 */
class PromptBuilder
{
    /**
     * System prompt untuk sebuah skill yang sudah dijalankan.
     */
    public static function build(string $skillName, SkillResult $result): string
    {
        $data    = $result->data;
        $hasData = self::hasUsableData($data);

        $s = "Kamu adalah AI Assistant akademik. Mode skill aktif: {$skillName}.\n";
        $s .= "Jawab dalam bahasa yang sama dengan yang dipakai user.\n\n";

        // ---- Aturan grounding: inti pertahanan anti-halusinasi ----
        $s .= self::groundingRules($hasData, $result->success);

        // ---- Data referensi ----
        if (! empty($data['guide'])) {
            $s .= "\n[PANDUAN SKILL]\n" . $data['guide'] . "\n";
        }

        if (! empty($data['modules']) && is_array($data['modules'])) {
            $s .= "\n[MODUL PANDUAN]\n";
            foreach ($data['modules'] as $module) {
                if (! is_array($module)) {
                    continue;
                }
                $s .= '- ' . ($module['module_name'] ?? '') . ': ' . ($module['description'] ?? '') . "\n";
            }
        }

        if (! empty($data['db_data'])) {
            $s .= "\n[DATA AKADEMIK]\n" . self::asText($data['db_data']) . "\n";
        }

        if (! empty($data['web_results'])) {
            $s .= "\n[HASIL WEB SEARCH]\n" . self::asText($data['web_results']) . "\n";
        }

        if (! empty($data['file_content'])) {
            $s .= "\n[ISI FILE]\n" . self::asText($data['file_content']) . "\n";
        }

        // ---- Skill gagal: larang keras menjawab dari ingatan ----
        if (! $result->success && $result->error) {
            $s .= "\n[STATUS PENCARIAN DATA]\n";
            $s .= 'GAGAL mengambil data. Alasan: ' . $result->error . "\n";
            $s .= "WAJIB: sampaikan bahwa data tidak dapat diambil. "
                . "JANGAN menjawab dari ingatan, dan JANGAN mengarang "
                . "angka, tanggal, atau nama apa pun.\n";

            if ($result->suggestion) {
                $s .= 'Saran untuk user: ' . $result->suggestion . "\n";
            }
        }

        // ---- Konteks waktu ----
        $s .= "\n" . AiClient::timeContext() . "\n";

        return $s;
    }

    /**
     * System prompt untuk obrolan umum tanpa data akademik.
     * Dipakai Api\Chat, Api\Discover::quickChat, dan jalur chat internal.
     */
    public static function general(string $role = 'asisten kampus yang ramah'): string
    {
        return "Kamu adalah {$role}.\n"
            . "Jawab dalam bahasa Indonesia yang santai, jelas, dan tidak kaku.\n"
            . "Jawab ringkas kecuali user meminta penjelasan panjang.\n\n"
            . self::groundingRules(false, true)
            . "\n" . AiClient::timeContext() . "\n";
    }

    /**
     * System prompt untuk skill menulis (butuh lebih kreatif, tapi tetap
     * tidak boleh mengarang fakta spesifik).
     */
    public static function writing(string $extra = ''): string
    {
        return "Kamu adalah asisten penulisan akademik.\n"
            . "Gaya boleh luwes dan kreatif, TETAPI:\n"
            . "- JANGAN mengarang data spesifik: nama orang, institusi, angka, "
            . "tahun, kutipan, referensi, atau DOI.\n"
            . "- Bila contoh dibutuhkan, pakai penanda jelas seperti [Nama], "
            . "[Tahun], [Institusi] agar user tahu itu harus diisi sendiri.\n"
            . "- Jangan mencantumkan sumber palsu.\n"
            . "- Jawab dalam bahasa yang sama dengan user.\n"
            . ($extra !== '' ? "\n" . $extra . "\n" : '')
            . "\n" . AiClient::timeContext() . "\n";
    }

    /**
     * Aturan wajib yang membuat model menolak mengarang.
     *
     * Publik karena dipakai juga oleh AssistantModule (jalur API akademik) agar
     * seluruh titik keluar AI memiliki pertahanan anti-halusinasi yang sama.
     *
     * @param bool $hasData apakah ada data referensi yang berhasil diambil
     * @param bool $ok      apakah skill berjalan sukses
     */
    public static function groundingRules(bool $hasData, bool $ok): string
    {
        $r = "[ATURAN WAJIB — PATUHI TANPA PENGECUALIAN]\n";

        $r .= "1. Jawab HANYA berdasarkan bagian [DATA AKADEMIK], [PANDUAN SKILL], "
            . "[MODUL PANDUAN], [HASIL WEB SEARCH], [ISI FILE], dan riwayat "
            . "percakapan yang disertakan.\n";

        $r .= "2. Jika jawabannya TIDAK ADA di bagian-bagian itu, balas: "
            . "\"Data tersebut tidak tersedia di sistem.\" lalu tawarkan bantuan lain. "
            . "DILARANG menebak, memperkirakan, atau mengisi dari pengetahuan umum.\n";

        $r .= "3. DILARANG KERAS mengarang: NIM/NPM, NIDN, NIK, nama orang, "
            . "nama mata kuliah, kode mata kuliah, tanggal, jadwal, nilai, IPK, "
            . "SKS, nominal uang, atau nomor apa pun.\n";

        $r .= "4. Saat menyebut angka, tanggal, atau nama yang berasal dari data, "
            . "tunjukkan asalnya, mis. \"(sumber: data KRS)\".\n";

        $r .= "5. Bila data tidak lengkap, ambigu, atau saling bertentangan, "
            . "katakan apa adanya dan tunjukkan bagian mana yang meragukan.\n";

        $r .= "6. Bedakan dengan tegas antara FAKTA dari data dan PENJELASAN "
            . "umum darimu. Jangan menyajikan penjelasan umum seolah-olah itu "
            . "data resmi institusi.\n";

        $r .= "7. Jangan pernah mengaku punya akses ke sistem, database, atau "
            . "informasi yang tidak tercantum di konteks ini.\n";

        if (! $hasData) {
            $r .= "8. PENTING: saat ini TIDAK ADA data yang berhasil diambil. "
                . "Jadi jawabanmu harus berupa penjelasan umum, panduan prosedur, "
                . "atau permintaan klarifikasi — BUKAN data spesifik tentang "
                . "siapa pun.\n";
        }

        if (! $ok) {
            $r .= "9. Pengambilan data mengalami kegagalan. Sebutkan bahwa data "
                . "tidak dapat diambil; jangan mengisi kekosongan itu dengan "
                . "jawaban karangan.\n";
        }

        return $r . "\n";
    }

    /** Apakah hasil skill memuat data yang benar-benar bisa dipakai? */
    private static function hasUsableData(array $data): bool
    {
        foreach (['db_data', 'web_results', 'file_content', 'guide', 'modules'] as $k) {
            if (! empty($data[$k])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Data bisa berupa string atau array. Ubah ke teks yang aman dibaca model.
     */
    private static function asText(mixed $v): string
    {
        if (is_string($v)) {
            return $v;
        }

        $json = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : (string) print_r($v, true);
    }
}
