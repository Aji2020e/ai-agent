<?php

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;

final class UserModelRememberTokenTest extends CIUnitTestCase
{
    public function testHasRememberTokenColumnReturnsFalseWhenColumnIsMissing(): void
    {
        $model = new class extends UserModel {
            public function __construct()
            {
                $this->table = 'users';
                $this->db = new class {
                    public function getFieldNames(string $table): array
                    {
                        return ['id', 'username', 'email'];
                    }
                };
            }
        };

        $this->assertFalse($model->hasRememberTokenColumn());
    }

    public function testHasRememberTokenColumnReturnsTrueWhenColumnExists(): void
    {
        $model = new class extends UserModel {
            public function __construct()
            {
                $this->table = 'users';
                $this->db = new class {
                    public function getFieldNames(string $table): array
                    {
                        return ['id', 'username', 'email', 'remember_token'];
                    }
                };
            }
        };

        $this->assertTrue($model->hasRememberTokenColumn());
    }
}
