<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Auth::login');

// Auth routes (registrasi publik DINONAKTIFKAN — akun dibuatkan admin)
$routes->group('', ['namespace' => 'App\Controllers'], static function ($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::attemptLogin');
    $routes->get('logout', 'Auth::logout');
});

// Web installer (tanpa login; dijaga InstallCheckFilter + cek isInstalled)
$routes->get('install', 'Install::index');
$routes->post('install/run', 'Install::run');

// Protected routes (require login)
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('dashboard', 'Dashboard::index');

    // Chat routes
    $routes->get('chat', 'Chat::index');
    $routes->get('chat/models', 'Chat::models');
    $routes->get('chat/(:segment)', 'Chat::session/$1');
    $routes->post('chat/send', 'Chat::send');
    $routes->post('chat/delete', 'Chat::deleteSession');
    $routes->post('chat/rename', 'Chat::renameSession');

    // Profile routes
    $routes->get('profile', 'Profile::index');
    $routes->post('profile/update', 'Profile::update');
    $routes->post('profile/change-password', 'Profile::changePassword');
});

// Admin routes (require admin role)
$routes->group('admin', ['filter' => 'admin'], static function ($routes) {
    $routes->get('users', 'Admin::users');
    $routes->post('users/create', 'Admin::createUser');
    $routes->post('users/toggle-status/(:num)', 'Admin::toggleUserStatus/$1');
    $routes->post('users/delete/(:num)', 'Admin::deleteUser/$1');

    // Skill Management
    $routes->get('skills', 'Skills::index');
    $routes->post('skills/toggle/(:num)', 'Skills::toggleSkill/$1');
    $routes->get('skills/(:num)/guides', 'Skills::guides/$1');
    $routes->post('skills/(:num)/guides/save', 'Skills::saveGuide/$1');
    $routes->get('skills/(:num)/modules', 'Skills::modules/$1');
    $routes->post('skills/(:num)/modules/save', 'Skills::saveModule/$1');
    $routes->get('skills/roles', 'Skills::roles');
    $routes->post('skills/roles/save', 'Skills::saveRoles');

    $routes->get('settings', 'Settings::index');
    $routes->post('settings/update', 'Settings::update');
    $routes->post('settings/test', 'Settings::test');
    $routes->get('api', 'AdminApi::index');
    $routes->post('api/clients', 'AdminApi::createClient');
    $routes->post('api/clients/hmac/(:num)', 'AdminApi::hmacClient/$1');
    $routes->post('api/clients/toggle/(:num)', 'AdminApi::toggleClient/$1');
    $routes->post('api/clients/model/(:num)', 'AdminApi::modelClient/$1');
    $routes->get('api/models', 'AdminApi::models');
    $routes->post('api/clients/delete/(:num)', 'AdminApi::deleteClient/$1');
    $routes->post('api/akademik', 'AdminApi::saveAkademik');
    $routes->post('api/akademik/test', 'AdminApi::testAkademik');
    $routes->post('api/docs', 'AdminApi::saveDoc');
    $routes->post('api/docs/upload', 'AdminApi::uploadDoc');
    $routes->post('api/docs/delete/(:num)', 'AdminApi::deleteDoc/$1');
    $routes->post('api/dict', 'AdminApi::saveDict');
    $routes->post('api/dict/delete/(:num)', 'AdminApi::deleteDict/$1');
    $routes->get('api/modules', 'AdminApi::modules');
    $routes->post('api/modules', 'AdminApi::saveModule');
    $routes->post('api/modules/delete/(:num)', 'AdminApi::deleteModule/$1');
    $routes->get('api/columns', 'AdminApi::columns');
    $routes->post('api/rolemap', 'AdminApi::saveRoleMap');
    $routes->post('api/modroles', 'AdminApi::saveModuleRoles');
    $routes->post('api/bagian', 'AdminApi::saveBagian');
});

// Machine-to-machine API (API key). Dibuka untuk aplikasi akademik.
$routes->group('api', ['filter' => 'apikey', 'namespace' => 'App\Controllers\Api'], static function ($routes) {
    $routes->get('status', 'Status::index');
    $routes->get('modules', 'Discover::modules');
    $routes->post('ask', 'Discover::ask');
    $routes->post('chat', 'Chat::send');
    $routes->post('mahasiswa/analyze', 'Mahasiswa::analyze');
    $routes->post('dosen/analyze', 'Dosen::analyze');
    $routes->post('staff/analyze', 'Staff::analyze');
    $routes->post('assistant/(:segment)/analyze', 'Assistant::analyze/$1');
});
