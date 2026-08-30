# Publicando o OurCloud Louvor no GitHub

Este arquivo é destinado ao mantenedor que fará a primeira publicação.

## 1. Crie um repositório vazio

No GitHub, crie:

```text
ourcloud-louvor
```

Recomendado:

- visibilidade: **Public**;
- não criar README automaticamente;
- não criar `.gitignore` automaticamente;
- não selecionar outra licença, pois `LICENSE` já contém AGPL v3.

## 2. Faça uma última verificação de segredos

Na raiz do projeto:

```bash
git status 2>/dev/null || true

find . \
  -name '.env' -o \
  -name '*.dump' -o \
  -name '*.sql' -o \
  -name '*.pem' -o \
  -name '*.key'
```

Procure padrões sensíveis:

```bash
grep -RInE \
  'APP_KEY=.+|DB_PASSWORD=.+|GOOGLE_CLIENT_SECRET=.+|MAIL_PASSWORD=.+' \
  . \
  --exclude-dir=.git \
  --exclude='.env.example' \
  --exclude='*.md'
```

Revise qualquer resultado antes de publicar.

## 3. Inicialize o Git

```bash
git init
git branch -M main
git add .
git status
```

Revise a lista inteira.

## 4. Primeiro commit

```bash
git commit -m "feat: publicar OurCloud Louvor como projeto open source"
```

## 5. Conecte ao GitHub

```bash
git remote add origin https://github.com/SEU_USUARIO/ourcloud-louvor.git
git push -u origin main
```

## 6. Ajuste links com `SEU_USUARIO`

Depois de saber o endereço final do repositório, substitua `SEU_USUARIO` em:

- `README.md`;
- `.github/ISSUE_TEMPLATE/config.yml`;
- outros documentos em que aparecer.

Faça um commit:

```bash
git add .
git commit -m "docs: ajustar links do repositório"
git push
```

## 7. Ative recursos do repositório

Em **Settings**:

- habilite Issues;
- habilite Discussions se desejar comunidade;
- confira Actions;
- em Security, habilite Private vulnerability reporting quando disponível;
- proteja a branch `main` se houver mais mantenedores.

## 8. Crie a primeira tag

```bash
git tag -a v1.0.0-rc88 -m "OurCloud Louvor v1.0.0-rc88"
git push origin v1.0.0-rc88
```

## 9. Crie a Release

No GitHub:

1. abra **Releases**;
2. clique em **Draft a new release**;
3. escolha `v1.0.0-rc88`;
4. título: `OurCloud Louvor v1.0.0-rc88`;
5. marque como **pre-release**, pois ainda é RC;
6. use `RELEASE_NOTES.md` como base;
7. anexe o ZIP de distribuição e seu SHA-256, se desejar.

## 10. Próximas contribuições

Use o fluxo:

```text
Issue → branch → Pull Request → CI → revisão → merge → release
```

Nunca faça commit diretamente de arquivos de produção, banco, `.env` ou segredos.
