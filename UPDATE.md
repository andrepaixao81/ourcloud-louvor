# Atualização do SGML

Este pacote contém um atualizador genérico e retomável para instalações que usam a arquitetura oficial em `/var/www/sgml`.

## 1. Faça backup antes

Pela interface, use **Administração > Backup e restauração > Gerar backup do banco** e baixe uma cópia para fora da VM.

O próprio atualizador também tenta criar um backup técnico em `/var/backups/sgml/pre-update`.

## 2. Descompacte a nova versão fora da aplicação

Exemplo:

```bash
cd /home/ubuntu
unzip OurCloud-Louvor-v1.0.0-rc94-GitHub.zip
cd ourcloud-louvor
```

Não descompacte por cima de `/var/www/sgml`.

## 3. Execute o atualizador

```bash
sudo bash deploy/update.sh
```

No pacote atual, o script faz primeiro um **pré-build isolado**:

1. confirma `/usr/bin/php8.3`;
2. executa o Composer explicitamente com PHP 8.3;
3. exige `config.platform.php = 8.3.6`;
4. resolve/instala dependências em `/var/tmp`, sem tocar na aplicação ativa;
5. valida que `vendor/autoload.php` realmente abre no PHP 8.3;
6. confirma Symfony HttpFoundation 7.4;
7. instala Node.js/npm automaticamente se estiverem ausentes;
8. compila o frontend em staging e exige `public/build/manifest.json`;
9. somente então entra em manutenção e troca a release;
10. executa migrations, caches, validações, fila, scheduler, PHP-FPM e Nginx.

Isso evita que uma falha de Composer ou `npm` deixe a instalação parcialmente atualizada.

## 4. Verifique

```bash
sudo bash /var/www/sgml/scripts/verify-install.sh
```

O final esperado é:

```text
VALIDAÇÃO TÉCNICA: APROVADA
```

## Arquivos lock

A edição pública versiona `composer.lock` e `package-lock.json`. Não remova esses arquivos de uma release: eles tornam Composer e npm reproduzíveis e permitem que o atualizador use `composer install` e `npm ci`.

Quando dependências forem atualizadas intencionalmente, atualize os locks em um Pull Request separado ou claramente documentado e execute a suíte de testes antes de publicar uma nova release.

## Evolution API e Meta Cloud API

As migrations são executadas automaticamente. As configurações de WhatsApp existentes são preservadas e o Administrador pode escolher o provedor em **Administração > Avisos pelo WhatsApp**.

## Permissões dos assets públicos

O pacote atual corrige um caso em que o frontend era compilado corretamente, porém `public/build` podia herdar o `umask 027` do processo de atualização e ficar em `0750/0640`. Como o Nginx executa como `www-data`, CSS e JavaScript retornavam 403 enquanto o HTML/PHP continuava funcionando.

O atualizador agora aplica explicitamente `0755` aos diretórios de `public/`, `0644` aos arquivos públicos, permite travessia da raiz da aplicação e valida os assets tanto como `www-data` quanto por HTTP antes de declarar sucesso.


## Recuperação de atualização interrompida

O atualizador tenta reativar automaticamente fila, scheduler, PHP-FPM, Nginx e retirar o Laravel de manutenção se uma falha ocorrer depois do início da janela de manutenção. Mesmo assim, sempre gere backup antes de atualizar.



## RC76 - Disponibilidade, exceção de mesmo dia e alertas

Depois da atualização:

1. em **Minha disponibilidade**, use a nova modalidade **Dias pares/ímpares do mês** quando não quiser vincular a regra a um dia da semana;
2. em **Escalas**, ao alterar manualmente um ministro, candidatos já escalados no mesmo dia aparecem com advertência, mas continuam selecionáveis se não houver sobreposição de horário;
3. ao adicionar um louvor em **Ensaios**, a equipe operacional recebe aviso;
4. ao criar um **Evento especial**, a equipe recebe mensagem com dia, data e horário e link para confirmar participação.

A RC76 possui uma migration aditiva para diferenciar o escopo das regras de disponibilidade. Ela é aplicada automaticamente por `deploy/update.sh`.

## RC81 - aniversários

A RC81 inclui uma migration que adiciona a data de nascimento ao cadastro de pessoas e as permissões de aniversários. O `deploy/update.sh` executa essa migration automaticamente. Depois da atualização, cada usuário pode informar a própria data em **Minha conta**.


## RC82 - Minha Agenda, perfis e desempenho

A RC82 aplica a migration de autorreparo de usuário/pessoa e índices de desempenho, simplifica a navegação do Ministro, adiciona Louvores programados e reduz trabalho concorrente na Agenda. O atualizador executa o smoke RC82 antes e depois do deploy.

## RC87 - runtime de backup e aniversariantes

A RC87 corrige automaticamente `/var/backups/sgml` para `root:sgml`/`0710` e `/var/backups/sgml/database` para `sgml:sgml`/`0700`, permitindo a travessia segura do usuário de serviço. Na atualização, não é necessário criar o diretório manualmente.

Em aniversariantes, somente o papel Membro comum fica sem visualização. Ministro, Participante do Louvor, Liderança/Pastor, Responsável pelo Ministério e Administrador permanecem autorizados.

## RC88 - edição open source

A RC88 não adiciona migration própria. Ela prepara o projeto para GitHub, adiciona licença AGPL, documentação comunitária, CI, exemplos genéricos e corrige o gerador automático de senha da instalação limpa.
