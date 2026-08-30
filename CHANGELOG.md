# Changelog

## [1.0.0-rc94] - 2026-08-30

### Melhorado

- editor de repertório reorganizado em três etapas visuais: Abertura opcional, Louvores da ministração e Ordem final;
- abertura deixa explícito que não é o Louvor 1 e fica recolhida quando já definida;
- sequência principal passa a usar linguagem inequívoca de Louvor 1, Louvor 2, Louvor 3...;
- ordem final diferencia visualmente `ABERTURA` dos louvores numerados;
- ministro vê contagem de louvores principais e total de itens;
- confirmação final passa a usar a ação “Repertório pronto? Avisar a equipe”;
- removida da interface a referência técnica a release RC84.

## [1.0.0-rc93] - 2026-08-30

### Melhorado

- WhatsApp ganha opção direta para remover falhas/cancelados sem reenviar;
- histórico de mensagens enviadas é preservado;
- pendências ainda em queue precisam ser canceladas em segurança antes de apagar o histórico não entregue.

## [1.0.0-rc92] - 2026-08-30

### Corrigido

- limpeza de testes não recria mais automaticamente dezenas de ocorrências recorrentes;
- opção explícita permite recriar recorrências somente quando desejado;
- limites administrativos deixam de compartilhar contador genérico, corrigindo 429 em limpar fila, limpeza operacional e exclusão de conta;
- nova página 429 amigável;
- fila WhatsApp mantém cancelamento seguro e teste síncrono.

## [1.0.0-rc91] - 2026-08-30

### Corrigido

- limpeza da fila WhatsApp e cancelamento de pendências;
- teste de WhatsApp imediato, sem depender do worker;
- remoção segura de contas com preservação de histórico;
- limpeza operacional de cultos/eventos refeita para respeitar FKs e cascatas;
- Eventos Especiais e avisos antigos removidos quando selecionados;
- falhas de reprojeção externa não mascaram uma limpeza local já concluída.

O projeto segue uma linha de release candidates enquanto a versão 1.0 é estabilizada.

## [1.0.0-rc90] - 2026-08-30

- Limpeza administrativa passa a remover opcionalmente Eventos Especiais de teste, respostas, avisos e logs relacionados.
- Administração ganha remoção segura de conta/usuário, preservando histórico e opcionalmente arquivando o cadastro pessoal.
- WhatsApp Evolution recebe espaçamento entre mensagens, backoff de retentativas e reenvio de falhas recentes.
- Tela de WhatsApp informa quando a API Key já está preservada; não é necessário digitá-la novamente após atualização.

## 1.0.0-rc89 - Recorrência canônica e limpeza operacional

- correção de ocorrências recorrentes duplicadas por diferença de fuso (ex.: cópias separadas por 3 horas);
- reconciliação passa a validar data, horário original, fuso e instante canônico da ocorrência;
- nova área **Administração → Limpeza de dados de teste**;
- backup PostgreSQL automático antes da limpeza destrutiva;
- remoção segura de escalas, atribuições, confirmações, trocas, repertórios planejados, execuções de teste, avisos operacionais e ocorrências recorrentes materializadas;
- preservação de usuários, pessoas, biblioteca de louvores, ensaios, tons lembrados, disponibilidade, permissões, tipos e regras dos cultos;
- cultos recorrentes futuros são recriados a partir das regras atuais;
- cultos avulsos/manuais somente são removidos mediante opção explícita; eventos especiais são preservados.

## 1.0.0-rc88 - GitHub/Open Source Edition

- adoção da licença AGPL-3.0;
- README público com instalação passo a passo;
- `.env.example` seguro;
- GitHub Actions para qualidade e testes;
- templates de Issues e Pull Requests;
- guias de contribuição, privacidade e segurança;
- catálogo embutido substituído por exemplos fictícios;
- remoção de referências operacionais específicas de uma igreja;
- correção do gerador automático de senha do instalador para evitar saída prematura com `pipefail`;
- preservação integral das funcionalidades da RC87.

## 1.0.0-rc87

- aniversariantes visíveis para Administrador, Responsável pelo Ministério, Ministro, Participante do Louvor e Liderança/Pastor;
- Membro comum sem acesso a aniversariantes;
- correção segura do runtime `/var/backups/sgml/database`.

## 1.0.0-rc85/rc86

- página 403 amigável e contextual;
- Central de Ações resiliente a mudanças de perfil;
- correção do modal global;
- evolução das regras de privacidade de aniversários.

## 1.0.0-rc84

- memória de tom por Ministro + Louvor;
- apoio ao ensaio;
- reaproveitamento do último tom conhecido;
- exposição de tom em repertório, agenda, calendário e PDF.

## 1.0.0-rc83

- revisão ampla de UI/UX;
- Minha Agenda como home operacional do ministro;
- navegação mobile e sidebar desktop;
- Central de Ações, repertório e formulários redesenhados.

## Releases anteriores

As RCs anteriores consolidaram agenda, escalas, recorrência, disponibilidade, trocas, repertório, histórico, WhatsApp, calendário, aniversários, backup/restauração e controle de acesso.
