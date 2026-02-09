# Run the app

to run the app locally just run:
php -S localhost:5000 -c php.ini

# Pre requisit

## MySql

1. Setup MySql
2. Setup db.php with the correct username/password and db server.
3. Make sure you enabled / uncommented the two feature in "etc/php/php.ini"
   extension=pdo_mysql
   extension=mysqli

#TODOs 0. Admin in drop down if < 1000 width
0.5. Confirme delete?

1. host in on host point
2. Get players so he can create start list.

CREATE DATABASE TPDb
CREATE USER 'TP'@'%' IDENTIFIED BY 'g0lf3lf4r0';
GRANT ALL PRIVILEGES ON TPDb.\* TO 'TP'@'%';
FLUSH PRIVILEGES;

insert into users (username, password, admin)
values ('bob', '$2y$10$JVO3RR7VBMM06dyYMkSPKeVDk8CmtrdXYMdSktSaX0sSjVw.kAmCG', 1)
