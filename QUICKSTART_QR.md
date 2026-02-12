# 🚀 Quick Start - Scanner QR Code

## ⚡ Instalação Rápida

1. **Extraia o ZIP** no diretório do seu servidor web
2. **Configure o `.env`** com suas credenciais
3. **Acesse o sistema** via navegador
4. **Pronto!** A funcionalidade QR já está ativa

> ⚠️ **Requisito:** Navegador moderno com suporte à câmera (Chrome, Firefox, Safari, Edge)

---

## 📱 Como Usar

### Na Tela de Contagem:

1. Clique no botão **"Scan QR"** (ícone de QR code verde)
2. Autorize o acesso à câmera quando solicitado
3. Posicione o QR code dentro da área marcada
4. **Pronto!** Os campos serão preenchidos automaticamente:
   - ✅ Depósito (3 primeiros caracteres)
   - ✅ Part Number (restante do código)
5. Digite apenas a **Quantidade**
6. Clique em **"Registrar Contagem"**

---

## 🏷️ Gerando QR Codes

### Método 1: Ferramenta Inclusa

Abra o arquivo **`gerador-qrcode.html`** no navegador:

1. Digite o depósito (3 caracteres, ex: **B9M**)
2. Digite o part number (ex: **555119496R**)
3. Clique em **"Gerar QR Code"**
4. Clique com botão direito na imagem → **Salvar** ou **Imprimir**

### Método 2: Online

Use sites como:
- https://www.qr-code-generator.com/
- https://www.qrcode-monkey.com/

**Formato do texto:**
```
B9M555119496R
└──┘└─────────┘
DEP  PARTNUMBER
```

---

## 📐 Formato do QR Code

```
Posição     | Conteúdo
------------|------------------
Chars 1-3   | Depósito (ex: B9M)
Char 4+     | Part Number (ex: 555119496R)
```

### ✅ Exemplos Válidos:

| QR Code | Depósito | Part Number |
|---------|----------|-------------|
| `B9M555119496R` | B9M | 555119496R |
| `A01ABC-123` | A01 | ABC-123 |
| `XY9ITEM2024` | XY9 | ITEM2024 |

### ❌ Exemplos Inválidos:

| QR Code | Problema |
|---------|----------|
| `AB` | Menos de 4 caracteres |
| `AB555` | Depósito com apenas 2 chars |
| ` B9M555` | Espaço no início |

---

## 🎯 Dicas de Uso

### Para melhor performance:

1. **Iluminação:** Use ambiente bem iluminado
2. **Distância:** Mantenha ~15-30cm do QR code
3. **Estabilidade:** Segure a câmera/dispositivo firme
4. **Qualidade:** Use impressão nítida (laser/térmica)
5. **Tamanho:** QR codes de 3x3cm ou maiores

### Troubleshooting:

| Problema | Solução |
|----------|---------|
| Câmera não abre | Verificar permissões do navegador |
| QR não é lido | Melhorar iluminação/foco |
| Campos errados | Verificar formato do código |
| Leitura lenta | Aproximar/afastar o código |

---

## 📦 Impressão de Etiquetas

### Recomendações:

- **Tamanho ideal:** 3x3 cm (mínimo 2x2 cm)
- **Material:** Etiquetas adesivas resistentes
- **Impressora:** Laser ou térmica
- **Layout:**

```
┌─────────────────┐
│  [QR 3x3cm]     │
│                 │
│  DEP: B9M       │
│  PN: 55511...   │
└─────────────────┘
```

---

## 🔧 Configuração Avançada

### Alterar tamanho do QR reader:

Em `public/assets/js/app.js`, linha ~15:

```javascript
qrbox: { width: 250, height: 250 },  // Altere para 300 ou 200
```

### Alterar taxa de frames (FPS):

```javascript
fps: 10,  // Aumente para 15-20 se câmera for boa
```

---

## 📚 Documentação Completa

- **README.md** - Visão geral do sistema
- **QRCODE_GUIDE.md** - Guia detalhado de QR codes
- **CHANGELOG_QRCODE.md** - Detalhes técnicos da implementação

---

## ✅ Checklist Pré-Produção

Antes de usar em produção, teste:

- [ ] Scanner abre corretamente
- [ ] Câmera é detectada
- [ ] QR code é lido com sucesso
- [ ] Campos são preenchidos corretamente
- [ ] Depósitos são reconhecidos
- [ ] Novo depósito funciona (opção "Outro")
- [ ] Funciona em dispositivo móvel
- [ ] Funciona em diferentes navegadores

---

## 🎉 Pronto!

Agora você pode:
- ✅ Gerar QR codes com `gerador-qrcode.html`
- ✅ Imprimir etiquetas para seu inventário
- ✅ Fazer contagens ultra-rápidas com scanner
- ✅ Reduzir erros de digitação
- ✅ Aumentar produtividade da equipe

**Dúvidas?** Consulte `QRCODE_GUIDE.md` para mais detalhes.
