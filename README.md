# Invento — Next.js

Migração completa da aplicação PHP **Invento** para **Next.js 14** (App Router) com TypeScript.

---

## ✨ Funcionalidades

- **Autenticação** por nome + matrícula (operadores) ou nome + `admin` + senha (administradores)
- **Dashboard** com estatísticas do inventário ativo
- **Contagem** com formulário de registro, autocomplete via API, scanner QR
- **Sistema de 3 contagens** com controle por fase (admin libera cada fase)
- **Notificações em tempo real** para admin via polling (45s)
- **Cadastros** de depósitos e part numbers (com importação CSV)
- **Inventários Concluídos** com paginação
- **Exportação** em XLSX, CSV e TXT
- Mesmo CSS/design do sistema PHP original

---

## 🛠 Stack

| Camada     | Tecnologia                             |
|------------|----------------------------------------|
| Frontend   | React 18 + Next.js 14 (App Router)     |
| Backend    | Next.js API Routes (Node.js)           |
| Banco      | MySQL (mesmo do PHP)                   |
| Sessão     | `iron-session` (cookie criptografado)  |
| Export     | `xlsx` (SheetJS)                       |
| Estilos    | CSS original preservado                |

---

## 🚀 Instalação

### 1. Instalar dependências

```bash
npm install
```

### 2. Configurar variáveis de ambiente

Copie o `.env.example` para `.env.local` e preencha:

```bash
cp .env.example .env.local
```

```env
DB_HOST=localhost
DB_PORT=3306
DB_USER=seu_usuario
DB_PASS=sua_senha
DB_NAME=inventario

ADMIN_PASSWORD=sua_senha_admin

SESSION_TIMEOUT=3600
ITEMS_PER_PAGE=20

# Gere com: node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
SESSION_SECRET=seu_segredo_de_32_caracteres
```

### 3. Banco de dados

O banco é criado/migrado **automaticamente** na primeira requisição.  
O mesmo schema do PHP é compatível — se já tem um banco, pode reutilizar.

### 4. Rodar em desenvolvimento

```bash
npm run dev
```

Acesse: http://localhost:3000

### 5. Build para produção

```bash
npm run build
npm start
```

---

## 📂 Estrutura

```
invento-nextjs/
├── app/
│   ├── api/
│   │   ├── auth/login/       # POST login
│   │   ├── auth/logout/      # GET logout
│   │   ├── ajax/             # GET autocomplete, notificações; POST liberar/encerrar
│   │   ├── cadastros/        # POST CRUD depósitos e partnumbers
│   │   ├── contagem/         # POST registrar contagem
│   │   ├── dashboard/        # POST criar/fechar inventário
│   │   └── exportar/         # GET exportar XLSX/CSV/TXT
│   ├── login/                # Página de login
│   ├── dashboard/            # Dashboard (admin + operador)
│   ├── contagem/             # Formulário + tabela de contagens
│   ├── cadastros/            # CRUD de depósitos e part numbers
│   └── inventarios-concluidos/ # Histórico de inventários
├── components/
│   ├── Navbar.tsx            # Barra de navegação
│   ├── Footer.tsx            # Rodapé
│   └── ModalFooter.tsx       # Modais reutilizáveis
├── lib/
│   ├── db.ts                 # Pool MySQL2
│   ├── session.ts            # iron-session config
│   ├── migrations.ts         # Criação automática das tabelas
│   └── models.ts             # Toda a lógica de negócio
└── public/
    └── assets/
        ├── css/app.css       # CSS original preservado
        ├── js/app.js         # JS original + extensões Next.js
        └── Ivento.png        # Logo
```

---

## 🔄 Mapeamento de rotas PHP → Next.js

| PHP (`?pagina=`)        | Next.js                        |
|-------------------------|--------------------------------|
| `login`                 | `/login`                       |
| `dashboard`             | `/dashboard`                   |
| `contagem`              | `/contagem`                    |
| `cadastros`             | `/cadastros`                   |
| `inventarios_concluidos`| `/inventarios-concluidos`      |
| `exportar`              | `/api/exportar`                |
| `ajax`                  | `/api/ajax`                    |
| `logout`                | `/api/auth/logout`             |

---

## 🗄 Banco de dados

As tabelas são criadas automaticamente na primeira execução:

- `usuarios` — usuários (admin e operadores)
- `inventarios` — inventários (aberto/fechado)
- `contagens` — registros de contagem com 3 fases
- `depositos_registrados` — depósitos cadastrados
- `partnumbers_registrados` — part numbers cadastrados
- `notificacoes_admin` — notificações de atividade para admin

---

## 🔐 Acesso

| Tipo       | Nome        | Matrícula | Senha                     |
|------------|-------------|-----------|---------------------------|
| Admin      | Administrador | `admin` | `ADMIN_PASSWORD` do .env  |
| Operador   | Qualquer    | Qualquer  | —                         |

---

## 📝 Notas Técnicas

- **Sem CSRF manual**: a autenticação via iron-session + cookie httpOnly + SameSite=Strict protege contra CSRF nas API routes
- **Migrações automáticas**: `lib/migrations.ts` roda no startup (via middleware ou primeira requisição à `/api`)
- **Sessions stateless**: iron-session usa cookie criptografado, sem necessidade de Redis/DB de sessão
- **Mesmo banco MySQL**: compatível com banco do PHP existente
