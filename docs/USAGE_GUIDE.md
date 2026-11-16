# Guia de Uso - CRUD de Moradores

## Como Testar Localmente

### Backend

#### 1. Verificar se tudo está funcionando

```bash
# Executar testes unitários
php artisan test tests/Unit/ResidentServiceTest.php

# Resultado esperado: 3/3 testes PASSED
```

#### 2. Explorar os arquivos

```
app/
├── Http/Controllers/Api/
│   └── ResidentController.php          ← Camada HTTP
├── Services/
│   ├── ResidentService.php              ← Lógica de negócio
│   └── StructureService.php             ← Lógica de blocos/unidades
├── Repositories/
│   ├── Contracts/ResidentInterface.php  ← Interface
│   └── Core/ResidentRepository.php      ← Implementação
└── Providers/
    └── AppServiceProvider.php           ← Registro DI
```

#### 3. Entender o fluxo

```php
// Controller recebe requisição
ResidentController::store($request, $condominium)
    ├── Valida permissão
    ├── Valida dados
    └── Delega para service
        ResidentService::createResident($user, $data)
            ├── Inicia transação
            └── Delega para repository
                ResidentRepository::createResident($data)
                ResidentRepository::createUnitResident($resident, $data)
```

### Frontend

#### 1. Começar

```bash
# Build para produção
npm run build

# Verificar se `/admin/moradores` aparece nas rotas
# Deve estar marcado como "○ (Static)"
```

#### 2. Explorar componentes

```
src/
├── app/admin/moradores/
│   └── page.tsx                 ← Página completa
├── services/
│   └── residents.ts             ← API client com tipagem
└── components/layout/
    └── sidebar.tsx              ← Menu atualizado
```

#### 3. Entender tipos

```typescript
// ResidentLink é a estrutura de um morador
type ResidentLink = {
  id: number;
  relationship_type: string;
  start_date: string;
  resident: {
    document: string;
    phone: string;
    user: { id: number; name: string; email: string };
  };
  unit: {
    number: string;
    block: { name: string } | null;
  };
};
```

---

## Adicionar Nova Funcionalidade

### Exemplo: Relatório de Moradores por Bloco

#### 1. Backend - Criar novo método no Service

```php
// app/Services/ResidentService.php
public function getResidentsByBlock(Block $block): Collection
{
    return UnitResident::whereHas('unit', fn ($q) => $q->where('block_id', $block->id))
        ->where('status', 'active')->whereNull('end_date')
        ->with(['resident.user', 'unit'])
        ->orderBy('start_date')
        ->get();
}
```

#### 2. Backend - Adicionar rota

```php
// routes/api.php
Route::get('/blocks/{block}/residents', [ResidentController::class, 'blockReport']);
```

#### 3. Backend - Implementar endpoint

```php
// app/Http/Controllers/Api/ResidentController.php
public function blockReport(Request $request, Block $block): JsonResponse
{
    $condominium = $block->condominium;
    $this->authorizeCondominium($request, $condominium);
    
    return response()->json($this->residentService->getResidentsByBlock($block));
}
```

#### 4. Frontend - Criar componente

```typescript
// src/services/residents.ts
export async function getBlockResidents(blockId: number): Promise<ResidentLink[]> {
  const result = await api(`/api/blocks/${blockId}/residents`);
  if (!Array.isArray(result) || !result.every(link)) {
    throw new AuthError("Dados inválidos.");
  }
  return result;
}
```

#### 5. Frontend - Usar em página

```typescript
const residents = await getBlockResidents(blockId);
residents.forEach(r => console.log(r.resident.user.name));
```

---

## Permissões e Autorização

### Como Funciona

```javascript
// Frontend (sidebar.tsx)
{user.can_manage_access ? [
  ...admin_only_items
] : null}

// Backend (ResidentController)
if (!user.can('manage-access') && !user_is_manager_of_condominium) {
  return abort(403);
}
```

### Adicionar Novo Perfil

1. Na tela "Identidade e Acessos", criar novo perfil (ex: "Gerente de Moradores")

2. Atribuir permissões necessárias

3. Conceder acesso ao usuário no condomínio com esse perfil

4. Se quiser reconhecer o novo perfil, adicione ao array em:
   ```php
   // app/Http/Controllers/Api/ResidentController.php
   in_array(mb_strtolower(trim($membership->role->name)), [
       'admin', 'administrador', 'síndico', 'sindico',
       'gerente de moradores', // novo
   ], true)
   ```

---

## Debugging

### Ver logs de requisição

```bash
# Backend - ativar logging
# Modificar config/logging.php para usar 'single' ou 'daily'

tail -f storage/logs/laravel.log
```

### Validar estrutura do banco

```bash
# Conectar ao banco
php artisan tinker

# Explorar dados
>>> $cond = Condominium::first();
>>> $cond->units()->count();
>>> $cond->blocks()->get();
>>> $resident = Resident::with('user')->first();
>>> $resident->unitResidents;
```

### Testar endpoint manualmente

```bash
# Usar curl
curl -X GET http://localhost:8000/api/resident-condominiums \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Estrutura de Dados

### Residents
```
residents table:
- id (PK)
- user_id (FK, unique) → Cada usuário é morador apenas uma vez
- document (string, compartilhado)
- phone (string, compartilhado)
- created_at, updated_at

Relações:
├── user (HasOne)
└── unitResidents (HasMany)
```

### UnitResidents
```
unit_residents table:
- id (PK)
- unit_id (FK)
- resident_id (FK)
- relationship_type (enum: proprietário, inquilino, etc)
- start_date (date)
- end_date (date, nullable)
- status (enum: active, inactive)
- created_at, updated_at

Relações:
├── unit (BelongsTo)
├── resident (BelongsTo)
└── Temporal: valid quando status='active' e end_date IS NULL
```

### Units
```
units table:
- id (PK)
- condominium_id (FK)
- block_id (FK, nullable)
- number (string)
- unit_type (string: apartamento, casa, sala, etc)
- status (enum: active, inactive)
- created_at, updated_at

Constraints:
├── Unique(number, block_id) per condominium
└── FK: block_id → blocks.condominium_id
```

---

## Performance

### Queries Otimizadas

```php
// ❌ Evite N+1
$residents = UnitResident::all();
foreach ($residents as $r) {
    echo $r->resident->user->name; // Query por item!
}

// ✅ Use eager loading
$residents = UnitResident::with(['resident.user', 'unit.block'])->get();
foreach ($residents as $r) {
    echo $r->resident->user->name; // Uma query!
}
```

### Índices do Banco

```sql
-- Essenciais
CREATE INDEX idx_residents_user_id ON residents(user_id);
CREATE INDEX idx_unit_residents_unit_id ON unit_residents(unit_id);
CREATE INDEX idx_unit_residents_resident_id ON unit_residents(resident_id);
CREATE INDEX idx_unit_residents_status ON unit_residents(status);

-- Multi-tenant
CREATE INDEX idx_units_condominium_id ON units(condominium_id);
CREATE INDEX idx_blocks_condominium_id ON blocks(condominium_id);
```

---

## Troubleshooting

### Erro: "O usuário não possui vínculo ativo com este condomínio"

**Causa**: Tentou criar morador com usuário que não tem vínculo com o condomínio

**Solução**:
1. Ir para "Identidade e acessos"
2. Conceder acesso ao usuário no condomínio
3. Atribuir um perfil
4. Tentar novamente

### Erro: "Dados compartilhados com outro condomínio só podem ser alterados pelo administrador geral"

**Causa**: Morador está em vários condomínios e você é admin local

**Solução**:
1. Login com admin@admin.com.br
2. Fazer alteração
3. OU: Remover morador do outro condomínio primeiro

### Erro: "Não é possível excluir um morador com outros vínculos ou registros relacionados"

**Causa**: Morador tem:
- Outro vínculo de unidade ativo
- Ocorrências registradas
- Reservas ativas

**Solução**:
1. Encerrar outros vínculos (editar e ajustar end_date)
2. Resolver ocorrências
3. Cancelar reservas
4. Tentar excluir novamente

---

## Padrões de Código

### No Controller

```php
// ✅ Bom
public function store(Request $request, Condominium $condominium): JsonResponse
{
    $this->authorizeCondominium($request, $condominium);
    $data = $request->validate([...]);
    $result = $this->service->create($data);
    return response()->json($result, 201);
}

// ❌ Ruim (lógica no controller)
public function store(Request $request): JsonResponse
{
    DB::transaction(function () {
        // Muita lógica aqui
        $resident = Resident::create(...);
        if ($resident->unitResidents()->exists()) {
            throw new Exception();
        }
        // Mais lógica
    });
}
```

### No Service

```php
// ✅ Bom
public function create(User $user, array $data): UnitResident
{
    return DB::transaction(function () use ($user, $data) {
        // Lógica de negócio
        $this->repositories->create($data);
    });
}

// ❌ Ruim (queries no service)
public function create(array $data): UnitResident
{
    User::find($data['user_id']); // Faça no repository
}
```

### No Repository

```php
// ✅ Bom
public function create(array $data): Resident
{
    return Resident::create($data);
}

// ❌ Ruim (lógica de negócio)
public function create(array $data): Resident
{
    if ($data['document'] === existing_document) {
        throw ValidationException(...); // Faça no service
    }
}
```

---

## Checklist de Desenvolvimento

- [ ] Criar arquivo Service
- [ ] Criar arquivo Repository (se necessário)
- [ ] Criar interface Repository (se necessário)
- [ ] Atualizar AppServiceProvider com binding
- [ ] Implementar método no Controller
- [ ] Atualizar rotas (routes/api.php)
- [ ] Validar permissões
- [ ] Adicionar testes unitários
- [ ] Adicionar testes de feature
- [ ] Atualizar frontend correspondente
- [ ] Documentar em docs/

---

## Recursos Úteis

- [Laravel Service Provider](https://laravel.com/docs/13.x/providers)
- [Repository Pattern](https://refactoring.guru/design-patterns/repository)
- [Multi-tenancy em Laravel](https://laravel.com/docs/13.x/eloquent-relationships)
- [React](https://react.dev/learn)
