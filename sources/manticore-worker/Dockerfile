FROM manticoresearch/manticore:dev-28.6.7@sha256:892579b1a2344127c79b1608edf441dbbb17c90aad209114b1f1049007781603

RUN apt-get --fix-broken install -y && echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && \
apt-get update && apt-get -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y curl php8.5-cli php8.5-mysql php8.5-curl php8.5-zip php8.5-xml php8.5-mbstring wget git \
    supervisor dnsutils && rm -rf /var/lib/apt/lists/*

# Both CVE-2026-109618 and CVE-2026-109619 were fixed in PHP 8.5.12.
RUN update-alternatives --set php /usr/bin/php8.5 && \
    php -r 'if (PHP_VERSION_ID < 80512 || PHP_VERSION_ID >= 80600) { exit(1); }'

COPY composer.json manticore.conf shutdown.sh starter.sh replica.php env_reader.php cluster_tables.php quorum.php quorum_safety.php quorum_fixer.sh /etc/manticoresearch/
COPY tests/ /tmp/tests/
COPY supervisord.conf /etc/supervisord.conf
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /dev/stdout

RUN cd /etc/manticoresearch/ && composer update && \
    php /tmp/tests/cluster_tables_test.php && \
    php /tmp/tests/quorum_safety_test.php && \
    rm -rf /tmp/tests

WORKDIR /etc/manticoresearch/

CMD ["./starter.sh"]
