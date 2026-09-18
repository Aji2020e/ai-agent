<?php

namespace App\Controllers;

use App\Models\SkillModel;
use App\Models\SkillGuideModel;
use App\Models\SkillModuleModel;
use App\Models\RoleSkillModel;


class Skills extends BaseController
{
    protected SkillModel $skillModel;
    protected SkillGuideModel $guideModel;
    protected SkillModuleModel $moduleModel;

    protected RoleSkillModel $roleSkillModel;

    public function __construct()
    {
        $this->skillModel  = new SkillModel();
        $this->guideModel  = new SkillGuideModel();
        $this->moduleModel = new SkillModuleModel();
        $this->roleSkillModel = new RoleSkillModel();
    }

    public function index()
    {
        $skills = $this->skillModel->orderBy('order_index', 'ASC')->findAll();

        return view('admin/skills/index', [
            'title'  => 'Manajemen Skill',
            'skills' => $skills,
        ]);
    }

    public function guides(int $skillId)
    {
        $skill  = $this->skillModel->find($skillId);
        $guides = $this->guideModel->getGuidesBySkill($skillId);

        return view('admin/skills/guides', [
            'title'  => 'Panduan Skill: ' . $skill['display_name'],
            'skill'  => $skill,
            'guides' => $guides,
        ]);
    }

    public function saveGuide(int $skillId)
    {
        $data = [
            'skill_id'    => $skillId,
            'title'       => $this->request->getPost('title'),
            'content'     => $this->request->getPost('content'),
            'section'     => $this->request->getPost('section') ?: 'system_prompt',
            'order_index' => (int) ($this->request->getPost('order_index') ?? 0),
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $id = $this->request->getPost('id');

        if ($id) {
            $this->guideModel->update($id, $data);
        } else {
            $this->guideModel->insert($data);
        }

        return redirect()->to("/admin/skills/{$skillId}/guides")
                         ->with('success', 'Panduan berhasil disimpan.');
    }

    public function modules(int $skillId)
    {
        $skill   = $this->skillModel->find($skillId);
        $modules = $this->moduleModel->getModulesBySkill($skillId);

        return view('admin/skills/modules', [
            'title'   => 'Modul Skill: ' . $skill['display_name'],
            'skill'   => $skill,
            'modules' => $modules,
        ]);
    }

    public function saveModule(int $skillId)
    {
        $config = $this->request->getPost('module_config');

        $data = [
            'skill_id'      => $skillId,
            'module_name'   => $this->request->getPost('module_name'),
            'description'   => $this->request->getPost('description'),
            'module_config' => $config,
            'is_active'     => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $id = $this->request->getPost('id');

        if ($id) {
            $this->moduleModel->update($id, $data);
        } else {
            $this->moduleModel->insert($data);
        }

        return redirect()->to("/admin/skills/{$skillId}/modules")
                         ->with('success', 'Modul berhasil disimpan.');
    }

    public function toggleSkill(int $skillId)
    {
        $skill = $this->skillModel->find($skillId);
        $this->skillModel->update($skillId, ['is_active' => ! $skill['is_active']]);

        return redirect()->to('/admin/skills')->with('success', 'Status skill berhasil diubah.');
    }

    public function roles()
    {
        $skills = $this->skillModel->orderBy('order_index', 'ASC')->findAll();
        $roles  = ['student', 'dosen', 'staff', 'admin'];
        $roleSkills = [];

        foreach ($roles as $role) {
            $roleSkills[$role] = $this->roleSkillModel->getAllowedSkills($role);
        }

        return view('admin/skills/roles', [
            'title'      => 'Role Skill',
            'skills'     => $skills,
            'roles'      => $roles,
            'roleSkills' => $roleSkills,
        ]);
    }

    public function saveRoles()
    {
        $post = $this->request->getPost('roles');

        if (! is_array($post)) {
            return redirect()->to('/admin/skills/roles')->with('error', 'Data tidak valid.');
        }

        $this->roleSkillModel->builder()->truncate();

        foreach ($post as $role => $skills) {
            if (! is_array($skills)) {
                continue;
            }

            $data = [];
            foreach (array_unique($skills) as $skill) {
                $data[] = [
                    'role'       => $role,
                    'skill_name' => $skill,
                ];
            }

            if (! empty($data)) {
                $this->roleSkillModel->insertBatch($data);
            }
        }

        return redirect()->to('/admin/skills/roles')->with('success', 'Mapping role-skill berhasil disimpan.');
    }
}
