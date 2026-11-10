# Research: SPEC-04 — Consulta de Saldo, Tríade do UseCase e Endpoint GET /balance

Este documento consolida as decisões arquiteturais, padrões de design e convenções adotadas na concepção do endpoint `GET /balance` e da tríade de caso de uso `GetBalance`, alinhadas às diretrizes do `AGENTS.md` e às clarificações acordadas em `spec.md`.

---

## 1. Tratamento de Erros de Validação 422 sem Cabeçalho `Accept: application/json`

### Contexto
O endpoint `GET /balance` opera na raiz da aplicação (via `apiPrefix: ''` configurado em `bootstrap/app.php`). No Laravel, o fluxo padrão de tratamento de `ValidationException` em `FormRequest` verifica `$request->expectsJson()`. Caso o cliente faça uma chamada HTTP sem enviar `Accept: application/json` (comportamento típico de clientes simples cURL, browsers ou chamadas `$this->get('/balance')` em testes), o Laravel tenta realizar um redirecionamento HTTP 302 com mensagens na sessão web em vez de responder com erro HTTP 422.

### Decisão
Sobrescrever o método `failedValidation` em `app/Http/Requests/Account/GetBalanceRequest.php` para disparar uma `HttpResponseException` encapsulando uma resposta JSON com status 422:
```php
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

protected function failedValidation(Validator $validator): void
{
    throw new HttpResponseException(
        response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422)
    );
}
```

### Racional
1. Garante que qualquer requisição que falhe na validação de entrada receba imediatamente status HTTP `422 Unprocessable Content` e payload JSON descritivo, independentemente dos cabeçalhos HTTP enviados pelo cliente.
2. Cumpre com 100% de previsibilidade os cenários de teste BDD definidos em `spec.md` (`Then o status da resposta deve ser 422`).

### Alternativas Consideradas
- **Depender apenas de `$request->expectsJson()`**: Rejeitado, pois chamadas cURL sem header explícito sofreriam redirecionamento `302 Found`, quebrando o contrato REST.
- **Configurar middleware global no Laravel**: Rejeitado para esta ação (YAGNI), concentrando o comportamento no próprio `FormRequest` da ação de consulta.

---

## 2. Isolamento de Domínio e Tradução de Exceções (`AccountNotFoundException`)

### Contexto
O manual `AGENTS.md` (§3.1 e §3.2) estabelece que exceções de domínio disparadas pelas camadas inferiores devem ser desacopladas de protocolos de transporte (HTTP) e mapeadas no Controller para os códigos e formatos adequados.

### Decisão
1. Criar a classe `App\Exceptions\AccountNotFoundException` como exceção simples de domínio estendendo `\Exception`, sem referências a códigos de status HTTP.
2. No `GetBalanceUseCase`, caso o repositório retorne `null` para o `accountId` pesquisado, disparar `throw new AccountNotFoundException(...)`.
3. No `GetBalanceController`, capturar especificamente `catch (AccountNotFoundException)` e retornar `response('0', 404)->header('Content-Type', 'text/plain; charset=UTF-8')`.

### Racional
1. O caso de uso permanece puro e testável unitariamente sem qualquer dependência do kernel HTTP do Laravel.
2. O controller assume integralmente a responsabilidade de adaptar o evento de negócio "conta não encontrada" para o contrato exigido pelo EBANX (`404 Not Found` com corpo `0` em `text/plain`).

### Alternativas Consideradas
- **UseCase retornar `null` ou DTO com saldo 0**: Rejeitado porque saldo zero é um valor financeiro válido de uma conta existente. Misturar conta inexistente com conta de saldo zero violaria invariantes de negócio e a tipagem estrita do Output DTO.
- **Lançar `ModelNotFoundException` ou `NotFoundHttpException` do Laravel no UseCase**: Rejeitado conforme `AGENTS.md` §3.1 para evitar acoplamento da camada de aplicação com exceções de framework.

---

## 3. Formatação Numérica do Saldo em `text/plain`

### Contexto
O take-home assignment do EBANX exige retornos no formato `text/plain` representando valores como `20`. No Model `Account`, o saldo é tipado e submetido a cast para `float`.

### Decisão
No `GetBalanceUseCaseOutput` e no `GetBalanceController`, serializar o saldo utilizando conversão direta para string `(string) $balance`:
```php
public function toValue(): string
{
    return (string) $this->balance;
}
```

### Racional
1. Em PHP, a conversão `(string) 20.0` resulta na representação `"20"`, enquanto `(string) 20.5` resulta em `"20.5"`.
2. Preserva a precisão numérica sem zeros decimais desnecessários para saldos inteiros, cumprindo com exatidão os exemplos contratuais do EBANX.

---

## 4. Idempotência Estrita da Operação GET

### Contexto
O `AGENTS.md` (§5.3) exige que endpoints sob o verbo `GET` destinem-se exclusivamente à consulta e jamais gerem efeitos colaterais ou alterem estados de armazenamento.

### Decisão
1. O método `GetBalanceUseCase::execute()` consome apenas `AccountRepositoryInterface::find($accountId)`.
2. Nenhuma chamada a métodos de escrita (`save()`, `delete()`, `reset()`) ocorre no fluxo.
3. Se a conta não existir, nenhuma conta é implicitamente criada.
4. Múltiplas requisições com os mesmos parâmetros devolvem respostas idênticas sem alterar a base de dados.

---

## 5. Roteamento em `routes/api.php`

### Contexto
Na SPEC-03, `routes/api.php` foi ativado com `apiPrefix: ''` no `bootstrap/app.php` para prover endpoints na raiz sob o grupo de middleware `api` (stateless, sem CSRF e sem sessão).

### Decisão
Registrar a rota em `routes/api.php`:
```php
Route::get('/balance', GetBalanceController::class);
```

### Racional
Mantém todas as rotas da API financeira agrupadas no pipeline stateless nativo, garantindo ausência de bloqueios por CSRF e isolamento de sessão.

