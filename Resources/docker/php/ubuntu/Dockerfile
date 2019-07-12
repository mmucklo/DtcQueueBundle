# For building PHP from source for debugging purposes (specifically used in troubleshooting Issue #98)
FROM ubuntu:19.10
ENV DEBIAN_FRONTEND=noninteractive
RUN apt update
RUN apt install -y apt-utils
RUN apt install -y perl-modules
RUN apt upgrade -y
RUN apt install -y libfreetype6-dev
RUN apt install -y git
RUN apt install -y vim
RUN apt install -y libzip-dev libldap2-dev libxml2-dev libpng-dev libicu-dev libbz2-dev libtidy-dev
RUN apt install -y libmemcached-dev
RUN apt install -y libssl-dev
RUN apt install -y build-essential
RUN apt install -y autoconf automake re2c libtool bison
RUN apt install -y curl wget
RUN apt install -y netcat
RUN git clone https://git.php.net/repository/php-src.git
RUN apt install -y sendmail gawk
RUN apt install -y libcurl4-openssl-dev zlibc libgd-dev libfreetype6 libjpeg9 libgdbm-dev libsodium-dev mysql-common mysql-client postgresql-11 libreadline5 libreadline-dev
RUN apt install -y libsqlite3-dev
