# Stage 1: Apache production image
FROM docker.io/library/httpd:2.4.62-alpine

# Copy custom Apache configuration
# -----------------------------------------------------------
# Replace the default Apache configuration with our custom one
# that is optimized for serving a Laravel application.
# -----------------------------------------------------------
COPY ./docker/production/apache/httpd.conf /usr/local/apache2/conf/httpd.conf

# Expose port 80 and start Apache
EXPOSE 80
CMD ["httpd-foreground"]
