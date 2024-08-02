FROM ubuntu:22.04

# Atualiza a lista de pacotes e instala dependências essenciais
RUN apt-get update && apt-get install -y \
    software-properties-common \
    wget \
    lsb-release \
    apt-transport-https \
    ca-certificates \
    gnupg \
    sudo \
    nano \
    curl

# Adiciona o repositório do PHP e instala PHP 8.3
RUN add-apt-repository ppa:ondrej/php && \
    apt-get update && \
    apt-get install -y \
    php8.3 \
    php8.3-cli \
    php8.3-fpm \
    php8.3-common \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-intl \
    php8.3-mysql \
    php8.3-sqlite3 \
    php8.3-curl \
    php8.3-gd \
    php8.3-opcache \
    php8.3-xmlrpc \
    php8.3-soap \
    php8.3-bcmath \
    php8.3-ctype \
    php8.3-iconv

# Instala o Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Verifica se o Composer foi instalado corretamente
RUN composer --version

# Instala o Symfony CLI
RUN wget https://get.symfony.com/cli/installer -O - | bash
ENV PATH="$HOME/.symfony/bin:$PATH"

# Instala o Tesseract OCR
RUN apt-get install -y tesseract-ocr

# Instala o Nginx
RUN apt-get install -y nginx

# Define o diretório de trabalho
WORKDIR /var/www/html

# Copia os arquivos do projeto para o diretório de trabalho
COPY . .

# Instala as dependências do projeto
RUN composer install --no-dev --optimize-autoloader || \
    { echo "Composer install failed"; cat /var/www/html/var/log/*.log; exit 1; }

# Instala pacotes adicionais do Symfony
RUN composer require symfony/webapp-pack || \
    { echo "Composer require symfony/webapp-pack failed"; cat /var/www/html/var/log/*.log; exit 1; }

RUN composer require symfony/annotations || \
    { echo "Composer require symfony/annotations failed"; cat /var/www/html/var/log/*.log; exit 1; }

# Define as permissões corretas para o diretório de cache e log
RUN chown -R www-data:www-data var/cache var/log

# Copia o arquivo de configuração do Nginx
COPY ./nginx/default /etc/nginx/sites-available/default

# Define a variável de ambiente
ENV APP_ENV=prod
ENV SYMFONY_DEPRECATIONS_HELPER=999999

# Expõe a porta 80
EXPOSE 80

# Comando para iniciar o Nginx e PHP-FPM
CMD service php8.3-fpm start && nginx -g 'daemon off;'