# Geração de QR Codes para o Sistema de Inventário

## Formato do QR Code

O sistema espera QR Codes no seguinte formato:

```
[DEPÓSITO (3 caracteres)][PARTNUMBER (restante)]
```

### Exemplos válidos:

| QR Code | Depósito | Part Number |
|---------|----------|-------------|
| `B9M555119496R` | B9M | 555119496R |
| `A01ABC-123-XYZ` | A01 | ABC-123-XYZ |
| `Z99ITEM-2024` | Z99 | ITEM-2024 |

## Gerando QR Codes

### Opção 1: Online (Recomendado para testes)

Use geradores gratuitos como:
- https://www.qr-code-generator.com/
- https://www.qrcode-monkey.com/
- https://www.the-qrcode-generator.com/

**Passos:**
1. Escolha tipo "Texto"
2. Digite o código no formato: `DEPPARTNUMBER`
3. Gere e imprima

### Opção 2: Python (Geração em lote)

```python
import qrcode
import csv

# Ler arquivo CSV com depósitos e partnumbers
with open('inventario.csv', 'r') as f:
    reader = csv.DictReader(f)
    
    for row in reader:
        deposito = row['deposito'][:3].upper()  # 3 primeiros caracteres
        partnumber = row['partnumber']
        
        # Gerar código
        codigo = f"{deposito}{partnumber}"
        
        # Criar QR Code
        qr = qrcode.QRCode(version=1, box_size=10, border=5)
        qr.add_data(codigo)
        qr.make(fit=True)
        
        img = qr.make_image(fill_color="black", back_color="white")
        img.save(f'qrcodes/{codigo}.png')
        
        print(f"QR Code gerado: {codigo}")
```

### Opção 3: JavaScript/Node.js

```javascript
const QRCode = require('qrcode');
const fs = require('fs');

async function gerarQRCode(deposito, partnumber) {
    const codigo = `${deposito.substring(0, 3).toUpperCase()}${partnumber}`;
    
    try {
        await QRCode.toFile(
            `qrcodes/${codigo}.png`,
            codigo,
            { width: 300, margin: 2 }
        );
        console.log(`QR Code gerado: ${codigo}`);
    } catch (err) {
        console.error('Erro:', err);
    }
}

// Exemplo de uso
gerarQRCode('B9M', '555119496R');
gerarQRCode('A01', 'ITEM-ABC-123');
```

### Opção 4: Excel/Google Sheets

1. Crie planilha com colunas: `Depósito | Part Number`
2. Crie coluna `QR Code` com fórmula: `=ESQUERDA(A2,3)&B2`
3. Use add-on "QR Code Generator" para gerar imagens

## Impressão de Etiquetas

### Recomendações:

- **Tamanho mínimo:** 2x2 cm (para leitura confiável)
- **Tamanho ideal:** 3x3 cm ou maior
- **Material:** Etiquetas adesivas resistentes
- **Impressão:** Laser ou térmica (melhor contraste)

### Layout sugerido para etiqueta:

```
┌─────────────────────┐
│  [QR CODE 3x3cm]    │
│                     │
│  Depósito: B9M      │
│  PN: 555119496R     │
└─────────────────────┘
```

## Testando QR Codes

Antes de imprimir em lote, teste alguns códigos:

1. Gere 2-3 QR codes de exemplo
2. Imprima ou exiba na tela
3. Teste com o scanner do sistema
4. Verifique se os campos são preenchidos corretamente

## Resolução de Problemas

### QR Code não é lido

- ✅ Verifique iluminação (evite reflexos)
- ✅ Mantenha câmera estável
- ✅ Aproxime/afaste para melhor foco
- ✅ Certifique-se que QR code está nítido

### Campos preenchidos incorretamente

- ✅ Verifique se código tem pelo menos 4 caracteres
- ✅ Confirme formato: 3 chars (depósito) + restante (partnumber)
- ✅ Verifique se não há espaços extras no código

### Depósito não encontrado na lista

- ✅ Sistema selecionará automaticamente "Outro"
- ✅ Campo de novo depósito será preenchido
- ✅ Você pode confirmar ou alterar antes de salvar
