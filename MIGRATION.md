# Migração do SGML para outra VM

Este procedimento serve para trocar de servidor, reinstalar uma VM ou recuperar o sistema após perda da máquina original.

A regra mais importante é:

> **Backup do banco + mesma APP_KEY + mesmo domínio = maior chance de recuperação transparente dos usuários, tokens e integrações.**


## Compatibilidade da versão do PostgreSQL

Antes de restaurar um dump em outra VM, confira a major do servidor de origem e do destino. **Não restaure um dump criado a partir de PostgreSQL mais novo em um servidor de major mais antiga.**

Exemplo de conferência na origem:

```bash
psql --version
sudo -u postgres psql -c "SELECT version();"
```

Na nova VM, use uma major igual ou mais nova que a origem. Se o Ubuntu instalar uma major mais antiga durante uma instalação temporária de migração, atualize o cluster PostgreSQL antes da restauração.

Depois de alterar a major do PostgreSQL, valide que o cluster correto está online na porta configurada no `.env`:

```bash
pg_lsclusters
```

## 1. Na VM antiga: gere o backup

Pela interface:

1. entre como Administrador;
2. abra **Administração > Backup e restauração**;
3. clique em **Gerar backup do banco**;
4. baixe o arquivo `.dump` para um computador seguro.

Não dependa apenas do arquivo que ficou dentro da VM antiga.

## 2. Na VM antiga: exporte a APP_KEY

A APP_KEY não é colocada dentro do backup do banco por segurança.

No terminal da VM antiga:

```bash
cd /var/www/sgml
sudo bash deploy/export-app-key.sh
```

Será criado:

```text
/root/sgml-app-key.txt
```

Transfira esse arquivo de forma segura para a nova VM. Ele é um segredo e não deve ir para GitHub, e-mail público ou chat de grupo.

## 3. Prepare a nova VM

Use Ubuntu Server 24.04 LTS.

Envie para a nova VM:

- o pacote/repositório OurCloud Louvor;
- `/root/sgml-app-key.txt`;
- o arquivo `.dump` do banco.

Exemplo com `scp` executado no seu computador:

```bash
scp sgml-app-key.txt ubuntu@IP_NOVO:/home/ubuntu/
scp sgml-db-AAAAmmdd_HHMMSS-xxxxxxxx.dump ubuntu@IP_NOVO:/home/ubuntu/
```

Na nova VM:

```bash
sudo mv /home/ubuntu/sgml-app-key.txt /root/sgml-app-key.txt
sudo chmod 600 /root/sgml-app-key.txt
```

## 4. Instale a nova VM usando a APP_KEY antiga

Enquanto o DNS ainda aponta para a VM antiga, você pode preparar a nova em HTTP:

```bash
sudo bash deploy/install.sh \
  --domain louvor.minhaigreja.org.br \
  --admin-email admin@minhaigreja.org.br \
  --organization "Minha Igreja" \
  --app-key-file /root/sgml-app-key.txt \
  --skip-https
```

A conta Administrador criada nessa instalação é temporária: ao restaurar o banco antigo, os usuários do backup voltarão a valer.

## 5. Importe e restaure o backup

Você pode usar a interface **Administração > Backup e restauração**.

Se o domínio ainda não aponta para a nova VM, abra temporariamente pelo IP público em HTTP. Como essa etapa contém credenciais, faça isso apenas durante a migração e em uma rede confiável.

Na tela:

1. clique em **Selecionar arquivo .dump**;
2. escolha o backup;
3. clique em **Importar e validar backup**;
4. confira SHA-256 e avisos;
5. escolha **Restaurar**;
6. informe a senha do Administrador atual;
7. digite `RESTAURAR` quando solicitado;
8. aguarde o processo terminar.

A restauração:

- coloca o SGML em manutenção;
- pausa fila e scheduler;
- gera um backup pré-restauração do banco novo;
- executa `pg_restore` em transação;
- executa migrations pendentes;
- limpa caches;
- reativa fila e scheduler.

## 6. Alternativa pelo terminal

Se a interface não estiver acessível:

```bash
sudo bash /var/www/sgml/deploy/restore-database-backup.sh \
  --file /home/ubuntu/sgml-db-AAAAmmdd_HHMMSS-xxxxxxxx.dump \
  --app-dir /var/www/sgml
```

O script pede confirmação antes de substituir o banco.

## 7. Mude o DNS

Quando a nova VM estiver pronta, altere o registro `A` do domínio para o IP da nova VM.

A propagação depende do TTL configurado no DNS.

## 8. Ative HTTPS na nova VM

Depois que o domínio resolver para o novo IP:

```bash
cd /var/www/sgml
sudo bash deploy/enable-https-letsencrypt.sh \
  --domain louvor.minhaigreja.org.br \
  --email admin@minhaigreja.org.br
```

## 9. Valide tudo

```bash
sudo bash /var/www/sgml/scripts/verify-install.sh
```

Depois confira pela interface:

- usuários;
- escalas;
- cultos;
- repertórios;
- eventos especiais;
- trocas;
- backup/restauração;
- fila de avisos;
- agenda do sistema.

## 10. Aplicativo Android / calendários

Se o aplicativo móvel utiliza o mesmo domínio e os tokens permanecem no banco restaurado, a troca de IP da VM não exige recompilar o aplicativo. O DNS passa a encaminhar o mesmo domínio para a nova máquina.

Se o banco for recriado do zero ou os tokens forem revogados, será necessário vincular novamente os dispositivos.

## 11. Apague a cópia temporária da APP_KEY

Depois da migração e após guardar a chave em cofre seguro:

```bash
sudo shred -u /root/sgml-app-key.txt
```

Nunca faça commit da APP_KEY no GitHub.
