## Arquitetura de Residentes - Diagrama de Fluxo

```
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Next.js)                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  /admin/moradores/page.tsx                                      │
│  ├── Seleção de condomínio                                      │
│  ├── Cadastro de blocos/torres                                  │
│  ├── Cadastro de unidades                                       │
│  ├── Criação de moradores                                       │
│  └── Edição/Exclusão com confirmação                            │
│                                                                 │
│  ↓ services/residents.ts                                        │
│  ├── listResidentCondominiums()                                 │
│  ├── getResidentOverview()                                      │
│  └── Runtime type validation                                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                              ↓ HTTP/JSON
┌─────────────────────────────────────────────────────────────────┐
│                    BACKEND (Laravel)                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ResidentController (Http/Controllers/Api)                      │
│  ├── index()      → GET  /residents                             │
│  ├── store()      → POST /residents                             │
│  ├── show()       → GET  /residents/{link}                      │
│  ├── update()     → PUT  /residents/{link}                      │
│  ├── destroy()    → DEL  /residents/{link}                      │
│  ├── storeBlock() → POST /blocks                                │
│  └── storeUnit()  → POST /units                                 │
│                                                                 │
│  ↓ HTTP Request Validation                                      │
│  └── Regras de validação Laravel                               │
│                                                                 │
│  ↓ Authorization Check (canManage)                              │
│  ├── user.can('manage-access')  [admin geral]                   │
│  └── user has role [Admin|Administrador|Síndico|Sindico]        │
│                                                                 │
│  ↓ Dependency Injection                                         │
│  ├── ResidentService                                            │
│  └── StructureService                                           │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                   SERVICES LAYER                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ResidentService                                                │
│  ├── createResident()     → Business Logic                      │
│  │   ├── Lock user row                                          │
│  │   ├── Find or create resident                                │
│  │   ├── Validate data match                                    │
│  │   ├── Check duplicate unit link                              │
│  │   └── Create unit_resident                                   │
│  ├── updateResident()     → Transactional                       │
│  │   ├── Check shared resident (other condominiums)             │
│  │   ├── Validate permission to edit                            │
│  │   └── Update both resident & link                            │
│  ├── deleteResident()     → Validation                          │
│  │   ├── Check other links exist                                │
│  │   ├── Check occurrences exist                                │
│  │   ├── Check reservations exist                               │
│  │   └── Delete if safe                                         │
│  └── getResidents()       → Data retrieval                      │
│                                                                 │
│  StructureService                                               │
│  ├── createBlock()        → Simple create                       │
│  ├── createUnit()         → With duplicate check                │
│  ├── getBlocks()          → Ordered by name                     │
│  └── getUnits()           → With relations                      │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                REPOSITORY LAYER                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ResidentRepository (implements ResidentInterface)              │
│  ├── getResidents(Condominium)                                  │
│  ├── createResident(array)                                      │
│  ├── createUnitResident(Resident, array)                        │
│  ├── updateResident(Resident, array)                            │
│  ├── updateUnitResident(UnitResident, array)                    │
│  ├── deleteResident(Resident): bool                             │
│  ├── deleteUnitResident(UnitResident): bool                     │
│  ├── residentExists(userId): bool                               │
│  └── findUnitResidentForUpdate(id)                              │
│                                                                 │
│  Interface Binding (AppServiceProvider):                        │
│  └── ResidentInterface → ResidentRepository                     │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                 DATABASE LAYER                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Tables:                                                        │
│  ├── condominiums                                               │
│  │   ├── id, name, document, status                             │
│  │   └── Multi-tenant context                                   │
│  │                                                              │
│  ├── blocks                                                     │
│  │   ├── id, condominium_id, name, code                         │
│  │   └── FK: condominium_id                                     │
│  │                                                              │
│  ├── units                                                      │
│  │   ├── id, condominium_id, block_id, number, unit_type        │
│  │   ├── FK: condominium_id, block_id                           │
│  │   └── Unique: (number, block_id) per condominium             │
│  │                                                              │
│  ├── users                                                      │
│  │   ├── id, name, email, password, is_system_admin             │
│  │   └── Global identity                                        │
│  │                                                              │
│  ├── residents                                                  │
│  │   ├── id, user_id, document, phone                           │
│  │   ├── FK: users (unique)                                     │
│  │   └── Shared between condominiums                            │
│  │                                                              │
│  ├── unit_residents                                             │
│  │   ├── id, unit_id, resident_id, relationship_type            │
│  │   ├── start_date, end_date, status                           │
│  │   ├── FK: unit_id, resident_id                               │
│  │   └── Temporal vínculo                                       │
│  │                                                              │
│  └── condominium_users                                          │
│      ├── id, condominium_id, user_id, role_id                   │
│      ├── status, started_at, ended_at                           │
│      └── Authorization context                                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Fluxo de Operações

### 1. Criar Morador

```
Frontend: POST /api/condominiums/{id}/residents
    ↓
ResidentController::store()
    ├── authorizeCondominium() → Verifica permissão
    ├── validate() → Valida entrada
    └── ResidentService::createResident(user, data)
        ├── DB::transaction()
        ├── User::lockForUpdate() → Evita race condition
        ├── Resident::firstOrCreate() ou find()
        ├── Valida dados coincidem
        ├── Valida duplicate unit_resident
        └── UnitResident::create()
            ↓
        Database: INSERT residents, unit_residents
            ↓
        Response 201: ResidentLink com relações carregadas
```

### 2. Editar Morador

```
Frontend: PUT /api/condominiums/{id}/residents/{link_id}
    ↓
ResidentController::update()
    ├── authorizeCondominium() → Verifica permissão
    ├── validate() → Valida entrada
    └── ResidentService::updateResident(resident, link, data)
        ├── DB::transaction()
        ├── Resident::lockForUpdate()
        ├── Verifica se morador está em outro condomínio
        ├── Se compartilhado: valida permissão admin geral
        ├── Repository::updateResident()
        └── Repository::updateUnitResident()
            ↓
        Database: UPDATE residents, unit_residents
            ↓
        Response 200: UnitResident atualizado
```

### 3. Deletar Morador

```
Frontend: DELETE /api/condominiums/{id}/residents/{link_id}
    ↓
ResidentController::destroy()
    ├── authorizeCondominium()
    ├── link() → Valida integridade
    └── ResidentService::deleteResident(link)
        ├── DB::transaction()
        ├── Resident::lockForUpdate()
        ├── Verifica other links exist
        ├── Verifica occurrences exist
        ├── Verifica reservations exist
        ├── Repository::deleteUnitResident() → Se seguro
        └── Repository::deleteResident() → Se seguro
            ↓
        Database: DELETE unit_residents, residents
            ↓
        Response 200: Morador excluído
        (User account preserved)
```

---

## Isolamento Multi-Tenant

```
┌─────────────────────────────┐
│   Condomínio A              │
├─────────────────────────────┤
│ Blocks: [1, 2]              │
│ Units:  [101, 102, 201]     │
│ Residents: [User1, User2]   │
│ User1 → Unit 101            │
│ User2 → Unit 102            │
└─────────────────────────────┘

┌─────────────────────────────┐
│   Condomínio B              │
├─────────────────────────────┤
│ Blocks: [1]                 │
│ Units:  [1, 2]              │
│ Residents: [User3]          │
│ User3 → Unit 1              │
└─────────────────────────────┘

Query Protection:
WHERE condominium_id = ? 

Controller Authorization:
if (!canManage(user, condominium)) abort(403)

Foreign Key Integrity:
units.condominium_id ← blocks.condominium_id
unit_residents ← units.condominium_id
```

---

## Segurança de Dados Compartilhados

```
Caso: User1 é morador em Condomínio A e B

ResidentLink A:                ResidentLink B:
├── resident_id = 1 ──┐       └──────────────────┐
│                     └─→ Resident ←──────────────┘
│                      (document, phone)

Operação: Admin Local (Cond A) altera documento

Validação:
├── resident.unitResidents().other_condominiums() = true
├── !user.can('manage-access') = true
└── data[document] !== resident.document = true
    ↓
❌ DENIED: "Dados compartilhados só admin geral pode alterar"

Operação: Admin Geral altera documento

Validação:
├── user.can('manage-access') = true
    ↓
✅ ALLOWED: UPDATE residents SET document = ?
    (Afeta ambos os condomínios)
```
