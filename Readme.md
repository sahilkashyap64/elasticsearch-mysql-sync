ES MySQL sync script

it use scroll api to fetch all data and store it in scv file and then import it in mysql

index.php contains these methods

Basic method of 
1)ES connection and SQl connection
  -check_elastic_status
  -count_index_and_store_info
  -elasticsearch_include_its_library_prepare_obj
  -create_pdo_connection
  -fetch_from_elastic_put_in_db


2) Convert ES json data to csv
  -jsonToCsv

3) To store data in csv and then in mysql
 -fetch_the_es_data_with_scroll_dump_csv : Get data using ES scroll api and save it     to csv

 -insert_csv_in_db : Data from csv to mysql   

 [Blog](https://dev.to/sahilkashyap64/sync-es-data-with-your-mysql-db-l5b)
