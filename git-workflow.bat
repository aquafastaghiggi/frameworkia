@echo off
setlocal

cd /d "%~dp0"

echo.
echo ==============================
echo   Git Workflow Helper
 echo ==============================
echo 1 - Atualizar do Andre para ca
 echo 2 - Subir suas alteracoes para seu GitHub
 echo 3 - Fazer tudo: atualizar, subir e abrir PR
 echo.
set /p choice=Escolha uma opcao ^(1/2/3^): 

if "%choice%"=="1" goto update
if "%choice%"=="2" goto up
if "%choice%"=="3" goto go

echo Opcao invalida.
goto end

:update
echo.
echo [1/3] Indo para main...
git checkout main || goto error

echo [2/3] Buscando atualizacoes do upstream...
git fetch upstream || goto error

echo [3/3] Mesclando upstream/main...
git merge upstream/main || goto error

echo.
echo Atualizacao concluida com sucesso.
goto end

:up
echo.
echo [1/3] Indo para branch colaboracao...
git checkout colaboracao || goto error

echo [2/3] Adicionando arquivos...
git add . || goto error

git diff --cached --quiet
if errorlevel 1 (
    echo [3/3] Criando commit...
    git commit -m "update" || goto error
) else (
    echo [3/3] Nenhuma alteracao para commit.
)

echo Enviando para origin/colaboracao...
git push origin colaboracao || goto error

echo.
echo Envio concluido com sucesso.
goto end

:go
echo.
echo [1/6] Indo para main...
git checkout main || goto error

echo [2/6] Buscando atualizacoes do upstream...
git fetch upstream || goto error

echo [3/6] Mesclando upstream/main...
git merge upstream/main || goto error

echo [4/6] Voltando para colaboracao...
git checkout colaboracao || goto error

echo [5/6] Adicionando arquivos...
git add . || goto error

git diff --cached --quiet
if errorlevel 1 (
    echo Criando commit...
    git commit -m "update" || goto error
) else (
    echo Nenhuma alteracao para commit.
)

echo [6/6] Enviando para origin/colaboracao...
git push origin colaboracao || goto error

echo Tentando criar Pull Request...
gh pr create --repo andreghiggi/frameworkia --base main --head aquafastaghiggi:colaboracao --title "update" --body "auto"
if errorlevel 1 (
    echo PR nao criada. Pode ja existir uma aberta, ou o gh pode nao estar autenticado.
) else (
    echo PR criada com sucesso.
)

goto end

:error
echo.
echo Ocorreu um erro. Verifique a mensagem acima.

:end
echo.
pause
endlocal
