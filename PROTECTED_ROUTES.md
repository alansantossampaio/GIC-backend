# 🛡️ Proteção de Rotas Frontend

## Visão Geral

Adicionar validação de permissões nas rotas para impedir acesso direto (ex: digitando URL na barra).

---

## Implementação com Middleware

### Passo 1: Criar Middleware de Autenticação

**Arquivo**: `src/middleware.ts`

```typescript
import { NextRequest, NextResponse } from "next/server";

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Permitir acesso público
  if (pathname.startsWith('/auth')) {
    return NextResponse.next();
  }

  // Requer autenticação
  if (pathname.startsWith('/admin')) {
    const session = request.cookies.get('laravel_session');
    if (!session) {
      return NextResponse.redirect(new URL('/auth/login', request.url));
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/admin/:path*', '/auth/:path*'],
};
```

---

### Passo 2: Criar Componente de Proteção de Rota

**Arquivo**: `src/components/protected-route.tsx`

```typescript
"use client";

import { useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useAuth } from "@/contexts/auth-context";
import { getUserPermissions, hasAccess, type Feature } from "@/services/permissions";

interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredFeature: Feature;
}

export function ProtectedRoute({ children, requiredFeature }: ProtectedRouteProps) {
  const router = useRouter();
  const pathname = usePathname();
  const { isAuthenticated } = useAuth();
  const [authorized, setAuthorized] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function checkAccess() {
      if (!isAuthenticated) {
        router.push('/auth/login');
        return;
      }

      try {
        const permissions = await getUserPermissions();
        if (hasAccess(permissions, requiredFeature)) {
          setAuthorized(true);
        } else {
          router.push('/admin/dashboard');
        }
      } catch (error) {
        console.error('Erro ao verificar permissões:', error);
        router.push('/admin/dashboard');
      } finally {
        setLoading(false);
      }
    }

    checkAccess();
  }, [isAuthenticated, requiredFeature, router]);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p>Verificando acesso...</p>
        </div>
      </div>
    );
  }

  if (!authorized) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600 mb-2">Acesso Negado</h1>
          <p className="text-gray-600 mb-4">Você não tem permissão para acessar esta página.</p>
          <a href="/admin/dashboard" className="text-blue-600 hover:underline">
            Voltar para Dashboard
          </a>
        </div>
      </div>
    );
  }

  return children;
}
```

---

### Passo 3: Usar ProtectedRoute nas Páginas

**Exemplo**: `src/app/admin/moradores/page.tsx`

```typescript
import { ProtectedRoute } from "@/components/protected-route";
import { ResidentsPage } from "@/components/pages/residents-page";

export default function Page() {
  return (
    <ProtectedRoute requiredFeature="moradores">
      <ResidentsPage />
    </ProtectedRoute>
  );
}
```

---

## Exemplo Completo: Todas as Páginas

### Dashboard
```typescript
// src/app/admin/dashboard/page.tsx
import { ProtectedRoute } from "@/components/protected-route";

export default function Page() {
  return (
    <ProtectedRoute requiredFeature="dashboard">
      <DashboardPage />
    </ProtectedRoute>
  );
}
```

### Moradores
```typescript
// src/app/admin/moradores/page.tsx
import { ProtectedRoute } from "@/components/protected-route";

export default function Page() {
  return (
    <ProtectedRoute requiredFeature="moradores">
      <ResidentsPage />
    </ProtectedRoute>
  );
}
```

### Condomínios
```typescript
// src/app/admin/condominios/page.tsx
import { ProtectedRoute } from "@/components/protected-route";

export default function Page() {
  return (
    <ProtectedRoute requiredFeature="condominios">
      <CondominiumsPage />
    </ProtectedRoute>
  );
}
```

### Ocorrências
```typescript
// src/app/ocorrencias/page.tsx
import { ProtectedRoute } from "@/components/protected-route";

export default function Page() {
  return (
    <ProtectedRoute requiredFeature="ocorrencias">
      <OccurrencesPage />
    </ProtectedRoute>
  );
}
```

---

## 🧪 Teste de Segurança

### Cenário 1: Morador tenta acessar /admin/moradores

1. Login com conta de morador
2. Tente acessar: `http://localhost:3000/admin/moradores`
3. Esperado: Redirecionado para `/admin/dashboard`
4. Mensagem: "Acesso Negado"

### Cenário 2: Admin acessa /admin/moradores

1. Login com conta de admin
2. Acesse: `http://localhost:3000/admin/moradores`
3. Esperado: Página carrega normalmente

### Cenário 3: Não autenticado tenta acessar /admin

1. Não faça login
2. Tente acessar: `http://localhost:3000/admin/dashboard`
3. Esperado: Redirecionado para `/auth/login`

---

## 🔒 Camadas de Proteção

```
1. Autenticação (Next.js Middleware)
   └─ Verificar se usuário está logado

2. Autorização (ProtectedRoute)
   └─ Verificar se tem permissão para feature

3. Visibilidade (Sidebar)
   └─ Ocultar menu não permitido

4. Backend (Laravel Middleware)
   └─ Validar permissão na API
```

---

## 📊 Checklist de Implementação

- [ ] Criar `src/middleware.ts`
- [ ] Criar `src/components/protected-route.tsx`
- [ ] Envolver página Dashboard com `<ProtectedRoute>`
- [ ] Envolver página Moradores com `<ProtectedRoute>`
- [ ] Envolver página Condomínios com `<ProtectedRoute>`
- [ ] Envolver página Ocorrências com `<ProtectedRoute>`
- [ ] Testar acesso com morador
- [ ] Testar acesso com admin
- [ ] Testar acesso sem login
- [ ] Validar redirecionamentos

---

## 🚀 Próximos Passos

1. ✅ Sistema de permissões (backend + frontend)
2. ⏳ Proteção de rotas frontend (este documento)
3. ⏳ Middleware de autorização (Laravel)
4. ⏳ Auditoria de acesso
5. ⏳ Testes de segurança

---

**Status**: 📋 **PLANEJADO**  
**Data**: 13/11/2026
