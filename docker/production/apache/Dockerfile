# Stage 1: Build assets
FROM debian AS builder

# Install Node.js and build tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    nodejs \
    npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Set working directory
WORKDIR /var/www

# Copy Laravel application code
COPY . /var/www

# Install Node.js dependencies and build assets
RUN npm install && npm run build

# Stage 2: Apache production image
FROM docker.io/library/httpd:2.4.62-alpine

# Copy custom Apache configuration
# -----------------------------------------------------------
# Replace the default Apache configuration with our custom one
# that is optimized for serving a Laravel application.
# -----------------------------------------------------------
COPY ./docker/production/apache/httpd.conf /usr/local/apache2/conf/httpd.conf

# Copy Laravel's public assets from the builder stage
# -----------------------------------------------------------
# We only need the 'public' directory from our Laravel app.
# -----------------------------------------------------------
COPY --from=builder /var/www/public /var/www/public

# Set the working directory to the public folder
WORKDIR /var/www/public

# Expose port 80 and start Apache
EXPOSE 80
CMD ["httpd-foreground"]
