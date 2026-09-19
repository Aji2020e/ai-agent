<?php

namespace App\Models;

use CodeIgniter\Model;
use Throwable;

class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'key', 'value',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getSetting(int $userId, string $key): ?string
    {
        $row = $this->where('user_id', $userId)
                    ->where('key', $key)
                    ->first();

        if ($row === null) {
            $row = $this->where('user_id', null)
                        ->where('key', $key)
                        ->first();
        }

        return $row['value'] ?? null;
    }

    public function setSetting(int $userId, string $key, string $value): bool
    {
        $existing = $this->where('user_id', $userId)
                         ->where('key', $key)
                         ->first();

        if ($existing) {
            return $this->update($existing['id'], ['value' => $value]);
        }

        return (bool) $this->insert([
            'user_id' => $userId,
            'key'     => $key,
            'value'   => $value,
        ]);
    }

    public function getUserSettings(int $userId): array
    {
        $rows = $this->where('user_id', $userId)->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    /** Setting global (user_id NULL) — dipakai untuk konfigurasi aplikasi. */
    /**
     * Cache settings global per-request.
     *
     * PERBAIKAN F10: sebelumnya setiap pemanggilan getGlobal() menjalankan satu
     * query. AiClient::currentConfig() sendiri memanggilnya 3-4 kali, sehingga
     * satu request chat bisa memicu belasan query yang hasilnya sama.
     *
     * @var array<string, string|null>|null
     */
    private static ?array $globalCache = null;

    public function getGlobal(string $key, ?string $default = null): ?string
    {
        if (self::$globalCache === null) {
            self::$globalCache = [];
            try {
                foreach ($this->where('user_id', null)->findAll() as $row) {
                    self::$globalCache[(string) $row['key']] = $row['value'] ?? null;
                }
            } catch (\Throwable) {
                // Tabel belum ada (sebelum migrasi) — jangan memutus request.
                self::$globalCache = [];
            }
        }

        $v = self::$globalCache[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    /** Buang cache. WAJIB dipanggil setelah settings diubah. */
    public static function flushCache(): void
    {
        self::$globalCache = null;
    }

    public function setGlobal(string $key, string $value): bool
    {
        $existing = $this->where('user_id', null)
                         ->where('key', $key)
                         ->first();

        $ok = $existing
            ? $this->update($existing['id'], ['value' => $value])
            : (bool) $this->insert([
                'user_id' => null,
                'key'     => $key,
                'value'   => $value,
            ]);

        // Tanpa ini, perubahan settings tidak terlihat sampai request berikutnya.
        self::flushCache();

        return (bool) $ok;
    }

    /** Ambil secret (didekripsi). Nilai lama plaintext tetap dibaca. */
    public function getSecret(string $key, ?string $default = null): ?string
    {
        $val = $this->getGlobal($key);

        if ($val === null || $val === '') {
            return $default;
        }

        if (str_starts_with($val, 'enc:')) {
            try {
                return service('encrypter')->decrypt(hex2bin(substr($val, 4)));
            } catch (Throwable) {
                return $default;
            }
        }

        return $val;
    }

    /** Simpan secret terenkripsi. String kosong = hapus. */
    public function setSecret(string $key, string $value): bool
    {
        if ($value === '') {
            return $this->setGlobal($key, '');
        }

        return $this->setGlobal($key, 'enc:' . bin2hex(service('encrypter')->encrypt($value)));
    }

    /** Ambil daftar secret sebagai JSON terenkripsi. Kosong = hapus. */
    public function setSecretList(string $key, array $values): bool
    {
        $filtered = array_values(array_filter($values, static fn ($v) => is_string($v) && $v !== ''));

        if ($filtered === []) {
            return $this->setSecret($key, '');
        }

        return $this->setSecret($key, json_encode($filtered, JSON_UNESCAPED_SLASHES));
    }

    /** Ambil daftar secret (array) yang disimpan sebagai JSON terenkripsi. */
    public function getSecretList(string $key): array
    {
        $raw = $this->getSecret($key, '[]');
        if ($raw === null || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(
                    array_filter(
                        array_map(static fn ($v) => is_string($v) ? trim((string) $v) : '', $decoded),
                        static fn ($v) => $v !== ''
                    )
                );
            }
        } catch (\Throwable) {
        }

        return [];
    }

    /**
     * Ambil peta kunci per identifier (base_url → list of keys).
     * Struktur tersimpan:
     *   {"https://api.openai.com": ["sk-key1", "sk-key2"], "https://api.groq.com/openai": ["gsk_key1"]}
     */
    public function getSecretMap(string $key): array
    {
        $raw = $this->getSecret($key, '[]');
        if ($raw === null || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                // Normalisasi: pastikan nilai adalah array string
                $result = [];
                foreach ($decoded as $url => $keys) {
                    if (empty($url) || ! is_array($keys)) {
                        continue;
                    }
                    $normalKeys = array_values(
                        array_filter(
                            array_map(static fn ($v) => is_string($v) ? trim((string) $v) : '', $keys),
                            static fn ($v) => $v !== ''
                        )
                    );
                    if (! empty($normalKeys)) {
                        $result[$url] = $normalKeys;
                    }
                }
                return $result;
            }
        } catch (\Throwable) {
        }

        return [];
    }

    /** Simpan peta kunci per identifier. Kosong = hapus semua. */
    public function setSecretMap(string $key, array $data): bool
    {
        $normalized = [];
        foreach ($data as $url => $keys) {
            if (empty($url)) {
                continue;
            }
            $normalKeys = array_values(
                array_filter(
                    array_map(static fn ($v) => is_string($v) ? trim((string) $v) : '', (array) $keys),
                    static fn ($v) => $v !== ''
                )
            );
            if (! empty($normalKeys)) {
                $normalized[$url] = $normalKeys;
            }
        }

        if ($normalized === []) {
            return $this->setSecret($key, '');
        }

        return $this->setSecret($key, json_encode($normalized, JSON_UNESCAPED_SLASHES));
    }
}
