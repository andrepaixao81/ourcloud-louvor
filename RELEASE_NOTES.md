# OurCloud Louvor v1.0.0-rc94

Release de UI/UX do editor de repertório, preservando integralmente as regras funcionais da RC93.

## Repertório - experiência do ministro

- fluxo visual em **3 etapas**:
  1. **Abertura (opcional)**;
  2. **Louvores da ministração**;
  3. **Ordem final e aviso à equipe**;
- a Abertura informa explicitamente que **não entra na numeração** e nunca é apresentada como Louvor 1;
- quando uma abertura já está definida, seu formulário fica recolhido para não ocupar espaço durante a inclusão dos louvores principais;
- os louvores principais são apresentados como **LOUVOR 1, LOUVOR 2, LOUVOR 3...**;
- nova prévia de Ordem final mostra a Abertura separada da sequência numerada;
- contadores distinguem louvores principais do total de itens;
- botão final passa a usar a linguagem **Repertório pronto? Avisar a equipe**;
- textos técnicos de release foram removidos da experiência do ministro;
- memória de tom por ministro, busca, cadastro rápido, reordenação, notas, links e Livewire permanecem inalterados.

## Banco de dados

A RC94 não adiciona migration.

## Compatibilidade

A RC94 atualiza diretamente a RC93 com `deploy/update.sh`.
