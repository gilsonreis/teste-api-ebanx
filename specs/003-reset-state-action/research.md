# Research: SPEC-03 — Reset de Estado, Tríade do UseCase e Endpoint POST /reset

Este documento consolida as decisões arquiteturais, padrões de design e convenções adotadas na concepção do endpoint `POST /reset` e da tríade de caso de uso `ResetState`, alinhadas às diretrizes do `AGENTS.md` e às clarificações acordadas em `spec.md`.

---

## 1. Roteamento de API Stateless no Laravel 11 (`apiPrefix: ''`)

### Contexto
O teste de contratação do EBANX exige que o endpoint responda exatamente em `POST /reset` (na raiz da aplicação, sem o prefixo `/api`). No Laravel 11, a convenção padrão para `routes/web.php` inclui o grupo de middleware `web` (que impõe proteção CSRF com `VerifyCsrfToken`), rejeitando requisições `POST` externas com erro HTTP `419 Page Expired`.

### Decisão
Configurar o roteamento de API no `bootstrap/app.php` utilizando `routes/api.php` com `apiPrefix: ''`:
```php
->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
    apiPrefix: '',
)
```

### Racional
1. O grupo de middleware `api` do Laravel é inerentemente stateless (sem cookies de sessão, sem tokens CSRF e sem estado de navegação).
2. Definir `apiPrefix: ''` permite que todos os endpoints do desafio (`/reset`, `/balance`, `/event`) residam naturalmente em `routes/api.php` respondendo nas rotas raiz exigidas pela especificação, sem necessidade de listas manuais de exceção CSRF em `bootstrap/app.php`.

### Alternativas Consideradas
- **Registrar em `routes/web.php` com `validateCsrfTokens(except: ['reset'])`**: Funciona, mas mistura rotas de API com o pipeline web (cookies de sessão, encriptação de cookies etc.), violando a separação semântica entre Web e API.
- **Manter prefixo `/api`**: Incompatível com o contrato do avaliador externo do EBANX, que dispara chamadas estritas para `http://localhost:8000/reset`.

---

## 2. Padrão Single-Action Controller (`ResetController`)

### Contexto
O manual `AGENTS.md` (§3.1) estabelece que controllers atuam estritamente como adaptadores de transporte HTTP e devem ser implementados preferencialmente como classes de ação única através do método mágico `__invoke`.

### Decisão
Criar `app/Http/Controllers/Account/ResetController.php` como classe final com método `__invoke(ResetStateUseCase $useCase): Response`:
```php
namespace App\Http\Controllers\Account;

use App\UseCases\Account\ResetState\ResetStateUseCase;
use App\UseCases\Account\ResetState\ResetStateUseCaseInput;
use Illuminate\Http\Response;

final class ResetController
{
    public function __invoke(ResetStateUseCase $useCase): Response
    {
        $input = new ResetStateUseCaseInput();
        $output = $useCase->execute($input);

        return response($output->toValue(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
```

### Racional
1. **Alta Coesão e Baixo Acoplamento**: O controller não acessa diretamente banco, cache ou redis; apenas delega ao caso de uso.
2. **Desacoplamento de Transporte**: O controller recebe o `ResetStateUseCaseOutput` e formata a resposta no padrão HTTP requerido (`200 OK`, `text/plain`, payload `OK`).

### Alternativas Consideradas
- **Controller com múltiplos métodos (`AccountController@reset`)**: Rejeitado conforme `AGENTS.md` §3.1 para evitar acoplamento de ações distintas e manter controllers enxutos e focados.

---

## 3. Tríade do Caso de Uso (`app/UseCases/Account/ResetState/`)

### Contexto
O `AGENTS.md` (§3.2) determina que cada operação de negócio resida em diretório dedicado contendo a tríade: o caso de uso, seu DTO de entrada e seu DTO de saída.

### Decisão
Estruturar o diretório `app/UseCases/Account/ResetState/` contendo:
1. **`ResetStateUseCaseInput`**: DTO `readonly` com `declare(strict_types=1);`. Permite instanciação sem argumentos ou via fábrica estática `create()` / `fromArray()`.
2. **`ResetStateUseCaseOutput`**: DTO `readonly` com tipagem estrita contendo a confirmação do reset (`public readonly bool $success = true;`) e método `toValue(): string` retornando `'OK'`.
3. **`ResetStateUseCase`**: Classe com método `execute(ResetStateUseCaseInput $input): ResetStateUseCaseOutput`, injetando exclusivamente `AccountRepositoryInterface` via construtor.

### Racional
1. Preserva a fronteira arquitetural entre transporte e domínio.
2. Facilita testes unitários puros sem necessidade de subir o framework HTTP ou fixtures complexas de requisição.

### Alternativas Consideradas
- **Executar limpeza direta no Controller**: Violação grave de arquitetura limpa e de `AGENTS.md` §3.1.
- **Retornar tipos primitivos (`bool` ou `string`) direto do UseCase**: Violação da regra de DTOs tipados de saída (`Output DTO`).

---

## 4. Tratamento Permissivo de Parâmetros e Payloads

### Contexto
Avaliadores automatizados e scripts de teste externos podem enviar requisições `POST /reset` com corpos vazios, payloads JSON arbitrários (`{}`) ou cabeçalhos diversos (`Content-Type: application/json`).

### Decisão
O endpoint `POST /reset` não terá validação restritiva de payload (dispensando `FormRequest` para campos inexistentes), operando de forma permissiva: qualquer corpo ou parâmetro de query enviado é desconsiderado, executando o reset normalmente e retornando `200 OK` com `'OK'`.

### Racional
Evita reprovação prematura em suítes de validação externa que injetam payloads padrão em todas as requisições `POST`.

---

## 5. Exceções e Tratamento de Erro

### Contexto
A operação de reset delega a `AccountRepositoryInterface::reset()`. Caso a camada física de armazenamento falhe (ex: queda do servidor Redis ou falha de disco no SQLite), como o erro deve se propagar?

### Decisão
Permitir que exceções de infraestrutura inesperadas borbulhem para o manipulador global de exceções do Laravel (`bootstrap/app.php`), resultando em resposta HTTP `500 Internal Server Error`. Casos de negócio não se aplicam a um reset incondicional.

---

## 6. Governança OpenAPI e `/docs`

### Contexto
Foi clarificado com o usuário (Sessão 2026-11-10) que a criação de `docs/openapi.yaml` e da rota `/docs` fica expressamente fora do escopo desta feature, sendo postergada para entrega dedicada.

