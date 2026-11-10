# Research: SPEC-05 — Processamento de Eventos Financeiros, Tríade do UseCase e Endpoint POST /event

Este documento consolida as decisões arquiteturais, padrões de design e convenções adotadas na concepção do endpoint `POST /event` e da tríade de caso de uso `ProcessEvent`, alinhadas às diretrizes do `AGENTS.md` e às clarificações acordadas em `spec.md`.

---

## 1. Atomicidade e Sequenciamento Transacional na Transferência (`transfer`)

### Contexto
O endpoint `POST /event` com `type: "transfer"` envolve a movimentação de fundos entre duas contas (`origin` e `destination`). De acordo com os requisitos do desafio EBANX e o princípio da consistência financeira (`AGENTS.md` §5.2), se a conta de origem não existir ou não tiver saldo suficiente, a operação inteira deve falhar sem qualquer alteração na conta de origem nem criação/modificação da conta de destino.

### Decisão
No `ProcessEventUseCase`, a transferência é executada estritamente no seguinte fluxo ordenado:
1. Buscar a conta de origem no repositório (`$origin = $this->repository->find($input->origin)`).
2. Se a origem for `null`, lançar imediatamente `AccountNotFoundException`.
3. Executar o débito no Model de origem via `$origin->withdraw($input->amount)` (o próprio Model lança `InsufficientFundsException` caso o saldo seja inferior ao montante).
4. Somente após a origem ter seu saldo debitado com sucesso em memória, buscar a conta de destino (`$destination = $this->repository->find($input->destination)`), instanciando uma nova conta com saldo 0 caso não exista.
5. Executar o crédito no Model de destino via `$destination->deposit($input->amount)`.
6. Persistir ambas as contas no repositório (`$this->repository->save($origin)` e `$this->repository->save($destination)`).

### Racional
1. A ordem das operações garante que qualquer falha (conta inexistente ou saldo insuficiente) ocorre **antes** de qualquer interação com a conta de destino e antes de qualquer chamada a `save()`.
2. Como as mutações nos Models ocorrem em instâncias locais antes da persistência, uma exceção disparada em passos anteriores interrompe o fluxo imediatamente, impedindo persistências parciais.
3. Não introduz acoplamento a transações de banco de dados (`DB::transaction`), permitindo que repositórios plugáveis em memória (`CacheAccountRepository`, `RedisAccountRepository` ou `EloquentAccountRepository`) funcionem de forma idêntica.

### Alternativas Consideradas
- **Buscar e criar/alterar o destino antes de debitar a origem**: Rejeitado, pois se a origem não tivesse saldo ou não existisse, a conta de destino já teria sido criada ou creditada indevidamente.
- **Forçar transação de banco de dados no UseCase**: Rejeitado, violando o princípio de Persistência Plugável (`AGENTS.md` §1.5 e §3.4), já que os drivers ativos operam em cache e Redis.

---

## 2. Mapeamento de Falhas de Negócio (`AccountNotFoundException` e `InsufficientFundsException`) para HTTP 404

### Contexto
O teste técnico do EBANX exige que, caso uma operação de débito não possa ser realizada (conta não encontrada ou saldo insuficiente), a resposta seja status `404 Not Found` com corpo `0` em `text/plain`.

### Decisão
1. Manter as exceções de domínio `AccountNotFoundException` e `InsufficientFundsException` puras (sem códigos HTTP embutidos).
2. No `ProcessEventController`, capturar simultaneamente ambas as exceções no bloco `catch (AccountNotFoundException | InsufficientFundsException)`:
```php
try {
    $output = $useCase->execute($input);
    return response()->json($output->toArray(), 201);
} catch (AccountNotFoundException | InsufficientFundsException) {
    return response('0', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
}
```

### Racional
1. Cumpre com exatidão o contrato REST e os cenários de teste automatizados do EBANX.
2. Mantém o Controller como o único adaptador de transporte responsável por traduzir exceções de aplicação em respostas HTTP específicas.

### Alternativas Consideradas
- **Retornar 422 ou 400 para saldo insuficiente**: Rejeitado conforme clarificação acordada em `spec.md`, pois o avaliador automatizado do EBANX espera `404 0` para qualquer operação de saque/transferência inválida.

---

## 3. Validação de Auto-Transferência (`origin === destination`) via FormRequest

### Contexto
Uma requisição de transferência onde `origin` e `destination` possuem o mesmo identificador (ex: `origin: "100"`, `destination: "100"`) pode causar comportamentos anômalos em repositórios de cache ou concorrência se a mesma chave for manipulada duas vezes no mesmo ciclo.

### Decisão
Adicionar a regra `different:origin` no campo `destination` dentro do `ProcessEventRequest`:
```php
'destination' => [
    'required_if:type,deposit,transfer',
    'string',
    'different:origin',
],
```

### Racional
1. Rejeição imediata na borda da aplicação (HTTP 422) antes de qualquer leitura ou escrita no repositório.
2. Segue o princípio da Validação Antecipada (`AGENTS.md` §3.1).

### Alternativas Consideradas
- **Permitir a operação e executar débito e crédito sequenciais**: Rejeitado, pois transfere complexidade desnecessária para a persistência e não reflete um cenário financeiro real.
- **Validar no UseCase**: Rejeitado, pois a consistência de entrada dos parâmetros de requisição pertence aos `FormRequests`.

---

## 4. Garantia de Resposta 422 em JSON sem Cabeçalho `Accept: application/json`

### Contexto
Requisições com payloads inválidos ou campos faltantes devem retornar JSON estruturado com status HTTP 422, mesmo quando o cliente omitir o cabeçalho `Accept: application/json`. No Laravel padrão, a omissão desse cabeçalho em rotas não-API aciona o comportamento de redirecionamento HTTP 302.

### Decisão
Sobrescrever `failedValidation` em `app/Http/Requests/Account/ProcessEventRequest.php`:
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
1. Padroniza o comportamento já adotado com sucesso no `GetBalanceRequest` (SPEC-04).
2. Garante que qualquer ferramenta de teste de API, cliente cURL ou script externo receba HTTP 422 com payload JSON em falhas de validação.

### Alternativas Consideradas
- **Confiar no middleware global**: Rejeitado para manter os FormRequests autônomos e explicitamente blindados contra desvios de transporte.

---

## 5. Serialização Condicional do DTO de Saída (`ProcessEventUseCaseOutput`)

### Contexto
O endpoint `POST /event` possui três formatos de resposta de sucesso distintos:
- `deposit`: retorna apenas `{"destination": {"id": "100", "balance": 10}}`
- `withdraw`: retorna apenas `{"origin": {"id": "100", "balance": 15}}`
- `transfer`: retorna ambos `{"origin": {"id": "100", "balance": 0}, "destination": {"id": "300", "balance": 15}}`

### Decisão
O DTO `ProcessEventUseCaseOutput` possui propriedades anuláveis fortemente tipadas e um método semântico `toArray()` que remove chaves nulas:
```php
final readonly class ProcessEventUseCaseOutput
{
    /**
     * @param array{id: string, balance: int|float}|null $origin
     * @param array{id: string, balance: int|float}|null $destination
     */
    public function __construct(
        public ?array $origin = null,
        public ?array $destination = null,
    ) {}

    /**
     * @return array<string, array{id: string, balance: int|float}>
     */
    public function toArray(): array
    {
        return array_filter([
            'origin' => $this->origin,
            'destination' => $this->destination,
        ], fn ($value) => $value !== null);
    }
}
```

### Racional
1. Garante que `json_encode` ou `response()->json($output->toArray(), 201)` no Controller gere a estrutura JSON exata esperada pelo cliente sem chaves espúrias com valor `null`.
2. Mantém o DTO imutável, fortemente tipado e com formato previsível.

### Alternativas Consideradas
- **Criar 3 DTOs de saída distintos (`DepositOutput`, `WithdrawOutput`, `TransferOutput`)**: Rejeitado (YAGNI), pois adiciona sobrecarga de arquivos e complexidade de tipos no UseCase sem ganho arquitetural tangível para uma ação única de processamento de eventos.

---

## 6. Registro da Rota em `routes/api.php`

### Contexto
A aplicação opera com rotas de API na raiz (`apiPrefix: ''` em `bootstrap/app.php`). O endpoint `POST /event` deve ser acessível via `/event` sem token CSRF.

### Decisão
Registrar a rota exclusivamente em `routes/api.php`:
```php
Route::post('/event', ProcessEventController::class);
```

### Racional
1. Garante que o pipeline de middleware `api` seja aplicado (stateless, sem sessão e sem CSRF).
2. Mantém todos os endpoints do assignment (`/reset`, `/balance`, `/event`) centralizados no mesmo arquivo de rotas.

