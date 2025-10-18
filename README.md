copy .env.example and rename it .env
create database name inventory_warehouses
give .env 
you have to have mysql / mariadb on your system
DB_USERNAME=your_database_user_name 
DB_PASSWORD=your_databse_password

start project you should have php 8.1 at least that laravel project 10 and apache installed on your system local 
run composer install
start in terminal project directory by run php artisan serve 
it well be on http://127.0.0.1:8000 if you don't run another 

then run php artisan key:generate for project key 

and run php artisan migrate for database tables 


and i put postman collection file inside project you have to import it in your postman 
i called it inventory_warehouses_api.postman_collection.json
