ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

RUN . /etc/os-release \
  && if [ "$VERSION_CODENAME" = "bullseye" ]; then \
       rm -f /etc/apt/sources.list.d/*; \
       printf 'deb http://archive.debian.org/debian bullseye main\ndeb http://archive.debian.org/debian bullseye-updates main\ndeb http://archive.debian.org/debian-security bullseye-security main\n' > /etc/apt/sources.list; \
       echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive; \
     fi

RUN apt-get update \
  && apt-get install -y --no-install-recommends unzip libjpeg62-turbo-dev libpng-dev libzip-dev \
  && docker-php-ext-configure gd --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" gd zip mysqli \
  && rm -rf /var/lib/apt/lists/*

RUN printf 'session.save_path = /tmp\nopcache.revalidate_freq = 0\n' > /usr/local/etc/php/conf.d/zz-e2e.ini
