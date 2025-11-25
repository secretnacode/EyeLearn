# EyeLearn Docker Configuration
FROM php:8.1-apache

ENV PYTHONUNBUFFERED=1 \
    EYE_TRACKING_HOST=0.0.0.0 \
    EYE_TRACKING_PORT=5000 \
    PYTHON_VERSION=3.11.9 \
    PYTHON_BUILD=20240415

# Install PHP extensions plus system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    build-essential \
    pkg-config \
    libgl1 \
    libglib2.0-0 \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli \
    && a2enmod rewrite proxy proxy_http

# Download a prebuilt standalone Python 3.11 and wire it up
RUN cd /tmp \
    && curl -L -o python.tar.gz https://github.com/indygreg/python-build-standalone/releases/download/${PYTHON_BUILD}/cpython-${PYTHON_VERSION}+${PYTHON_BUILD}-x86_64-unknown-linux-gnu-install_only.tar.gz \
    && mkdir -p /opt/python3.11 \
    && tar -xzf python.tar.gz -C /opt/python3.11 --strip-components=1 \
    && rm python.tar.gz \
    && ln -sf /opt/python3.11/bin/python3.11 /usr/local/bin/python3 \
    && ln -sf /opt/python3.11/bin/pip3.11 /usr/local/bin/pip3 \
    && /opt/python3.11/bin/python3.11 -m ensurepip --upgrade \
    && rm -rf /var/lib/apt/lists/*

# Pre-install Python requirements for faster container start
COPY python_services/requirements.txt /tmp/requirements.txt
RUN python3 -m pip install --no-cache-dir -r /tmp/requirements.txt \
    && rm /tmp/requirements.txt

# Copy application files
COPY . /var/www/html/

# Set permissions and Apache config
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Entry point launches Python service + Apache
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose HTTP and eye-tracking ports
EXPOSE 80 5000

CMD ["/usr/local/bin/docker-entrypoint.sh"]
