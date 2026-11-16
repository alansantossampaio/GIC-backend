# 🔐 Solução: "The email has already been taken"

## Problema

Ao tentar convidar um usuário pela segunda vez (mesma conta), recebia erro:

```
The email has already been taken.
```

---

## 🔍 Análise

### Causa Original

No `AccessManagementController::invite()`, havia validação rígida:

```php
'email' => ['required', 'email', 'max:255', 'unique:users,email'],
```

**Problema**: 
- Primeira vez: Criar novo usuário ✅
- Segunda vez: Email já existe na tabela → **ERRO** ❌

---

## ✅ Solução Implementada

### Novo Comportamento

Permitir **re-convites para usuários que nunca confirmaram** sua conta:

```
Caso 1: Email NÃO existe
  → Criar novo usuário
  → Enviar convite

Caso 2: Email existe MAS não confirmou (email_verified_at IS NULL)
  → Reutilizar usuário (atualizar nome se mudou)
  → Enviar novo link de convite
  
Caso 3: Email existe E confirmou (email_verified_at NOT NULL)
  → ERRO: "Este usuário já confirmou sua conta"
```

### Código Refatorado

```php
public function invite(Request $request): JsonResponse
{
    $request->merge(['email' => Str::lower((string) $request->input('email'))]);
    $email = $request->input('email');
    
    // Validação sem 'unique' rule
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
    ]);

    $user = DB::transaction(function () use ($data, $email) {
        $existing = User::where('email', $email)->first();
        
        if ($existing) {
            // ❌ Usuário já confirmou? Não permitir re-convite
            if ($existing->email_verified_at !== null) {
                throw ValidationException::withMessages([
                    'email' => ['Este usuário já confirmou sua conta.']
                ]);
            }
            // ✅ Apenas convite não confirmado? Reutilizar e atualizar
            $user = $existing;
            $user->update(['name' => $data['name']]);
        } else {
            // ✅ Email novo? Criar usuário
            $user = User::create($data + ['password' => Str::random(64)]);
        }
        
        // Enviar link de senha/convite
        $status = Password::sendResetLink(['email' => $user->email], 
            function (User $user, string $token): void {
                $user->notify(new PasswordLink($token, invitation: true));
            }
        );
        
        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => ['Não foi possível enviar o convite.']
            ]);
        }

        return $user;
    });

    return response()->json([
        'id' => $user->id, 
        'message' => 'Convite enviado.',
        'is_reinvite' => $user->wasRecentlyCreated === false  // Indica se foi re-convite
    ], 201);
}
```

---

## 📋 Detalhes Técnicos

### Segurança

1. **Transação ACID**: Toda operação é atômica
2. **Validação de Email**: Formato validado mesmo sem `unique`
3. **Proteção contra Spam**: Apenas re-convidar quem não confirmou
4. **Senha Aleatória**: Usuário precisa definir senha pelo link

### Fluxo

```
Frontend: Enviar email (nome + email)
          ↓
BackEnd: POST /api/access-management/invite
         ↓
Validar: Nome (obrigatório), Email (formato válido)
         ↓
Transação:
  1. Buscar usuário com esse email
  2. Se existe:
     - Email verificado? → Erro "já confirmou"
     - Não verificado? → Atualizar nome
  3. Se não existe → Criar novo
  4. Enviar link de convite por email
  5. Retornar id do usuário + flag is_reinvite
```

---

## 🧪 Como Testar

### Cenário 1: Primeiro Convite

```bash
POST /api/access-management/invite
Content-Type: application/json

{
  "name": "João Silva",
  "email": "joao@exemplo.com"
}

# Resposta esperada:
{
  "id": 123,
  "message": "Convite enviado.",
  "is_reinvite": false
}
```

✅ Email recebe link para definir senha

---

### Cenário 2: Re-convite (sem confirmar)

```bash
POST /api/access-management/invite
Content-Type: application/json

{
  "name": "João Silva",  # Pode mudar o nome
  "email": "joao@exemplo.com"  # Mesmo email
}

# Resposta esperada:
{
  "id": 123,
  "message": "Convite enviado.",
  "is_reinvite": true  # ← Flag indica que foi re-convite
}
```

✅ Email recebe **novo link** (anterior expirou)  
⚠️ Campo `is_reinvite: true` indica que foi re-convite

---

### Cenário 3: Convite de Usuário Confirmado (Erro)

```bash
POST /api/access-management/invite
Content-Type: application/json

{
  "name": "João Silva",
  "email": "usuario-confirmado@exemplo.com"  # Já fez login
}

# Resposta esperada (HTTP 422):
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Este usuário já confirmou sua conta."]
  }
}
```

❌ Erro legível no frontend

---

## 🔄 Integração Frontend

No formulário de convites, você pode:

```typescript
// Se is_reinvite === true, mostrar mensagem diferente:
if (response.is_reinvite) {
  toast.info("Um novo link de convite foi enviado");
} else {
  toast.success("Convite enviado para novo usuário");
}
```

---

## 📊 Casos de Uso

| Cenário | Resultado | Mensagem |
|---------|-----------|----------|
| Email novo | ✅ Criar usuario | "Convite enviado" |
| Email existente, não verificado | ✅ Re-convidar | "Convite enviado" |
| Email existente, verificado | ❌ Erro | "Já confirmou sua conta" |

---

## 🛡️ Validações Aplicadas

| Campo | Regra | Motivo |
|-------|-------|--------|
| name | required, string, max:255 | Validação padrão |
| email | required, email, max:255 | Validar formato (sem unique) |
| name (atualização) | Sobrescreve se re-convite | Permitir atualização de nome |
| email_verified_at | Bloqueia se NOT NULL | Impedir re-convite confirmados |

---

## 📝 Arquivos Modificados

- ✅ `app/Http/Controllers/Api/AccessManagementController.php` - Refatorado método `invite()`

---

## 🎯 Próximos Passos

1. ✅ Testar cenário de re-convite
2. ⏳ Monitorar logs de envio de email
3. ⏳ Adicionar testes unitários para os 3 cenários
4. ⏳ Considerar adicionar limite de re-convites (ex: máx 3 tentativas)

---

**Status**: ✅ **CORRIGIDO**  
**Data**: 13/11/2026  
**Commits**: `Permitir re-convites para usuários não confirmados`
