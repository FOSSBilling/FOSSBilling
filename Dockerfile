FROM pensiero/apache-php-mysql:php7.4
RUN apt-get install nano -y
ADD ./build/source /var/www/public