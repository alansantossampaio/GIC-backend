<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\AccessManagementInterface;

class PermissionService
{
    public function __construct(private AccessManagementInterface $access) {}
    /**
     * Define os acessos disponíveis por perfil/role
     */
    private const ROLE_PERMISSIONS = [
        'admin' => [
            'dashboard',
            'condominios',
            'moradores',
            'funcionarios',
            'prestadores',
            'financeiro',
            'documentos',
            'ocorrencias',
            'relatorios',
            'configuracoes',
            'acessos',
            'notificacoes',
            'perfil',
        ],
        'administrador' => [
            'dashboard',
            'condominios',
            'moradores',
            'funcionarios',
            'prestadores',
            'financeiro',
            'documentos',
            'ocorrencias',
            'relatorios',
            'configuracoes',
            'acessos',
            'notificacoes',
            'perfil',
        ],
        'sindico' => [
            'dashboard',
            'condominios',
            'moradores',
            'funcionarios',
            'prestadores',
            'financeiro',
            'documentos',
            'ocorrencias',
            'relatorios',
            'configuracoes',
            'acessos',
            'notificacoes',
            'perfil',
        ],
        'morador' => [
            'dashboard',
            'ocorrencias',
            'notificacoes',
            'perfil',
        ],
        'visitante' => [
            'dashboard',
            'ocorrencias',
            'notificacoes',
            'perfil',
        ],
        'funcionario' => [
            'dashboard',
            'ocorrencias',
            'notificacoes',
            'perfil',
        ],
        'prestador' => [
            'dashboard',
            'ocorrencias',
            'notificacoes',
            'perfil',
        ],
    ];

    /**
     * Obter permissões do usuário para um condomínio específico
     */
    public function getUserPermissions(User $user, ?int $condominiumId = null): array
    {
        // Usuários admin do sistema têm acesso a todas as features
        if ($user->is_system_admin) {
            return self::getAllFeatures();
        }

        $memberships = $this->access->membershipsWithRoles($user);

        if ($condominiumId) {
            $memberships = $memberships->where('condominium_id', $condominiumId);
        }

        $roles = $memberships
            ->map(fn ($membership) => $membership->role->name)
            ->unique()
            ->values();

        $permissions = collect();

        foreach ($roles as $role) {
            $rolePermissions = self::ROLE_PERMISSIONS[str_replace('í', 'i', mb_strtolower(trim($role)))] ?? self::ROLE_PERMISSIONS['morador'];
            $permissions = $permissions->merge($rolePermissions);
        }

        return $permissions->unique()->values()->all();
    }

    /**
     * Verificar se usuário tem acesso a uma feature
     */
    public function hasAccess(User $user, string $feature, ?int $condominiumId = null): bool
    {
        $permissions = $this->getUserPermissions($user, $condominiumId);
        return in_array($feature, $permissions);
    }

    /**
     * Obter lista de features acessíveis
     */
    public function getAccessibleFeatures(User $user, ?int $condominiumId = null): array
    {
        return $this->getUserPermissions($user, $condominiumId);
    }

    /**
     * Verificar se é admin ou síndico
     */
    public function isAdmin(User $user): bool
    {
        // Usuários admin do sistema são sempre admin
        if ($user->is_system_admin) {
            return true;
        }

        $roles = $this->access->membershipsWithRoles($user)
            ->map(fn ($m) => str_replace('í', 'i', mb_strtolower(trim($m->role->name))))
            ->unique();

        return $roles->contains('admin') || 
               $roles->contains('administrador') || 
               $roles->contains('sindico');
    }

    /**
     * Retornar todas as features disponíveis
     */
    public function getAllFeatures(): array
    {
        return [
            'dashboard',
            'condominios',
            'moradores',
            'funcionarios',
            'prestadores',
            'financeiro',
            'documentos',
            'ocorrencias',
            'relatorios',
            'configuracoes',
            'acessos',
            'notificacoes',
            'perfil',
        ];
    }
}
