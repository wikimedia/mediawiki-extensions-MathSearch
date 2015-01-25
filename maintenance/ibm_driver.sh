#!/bin/bash
echo "Warning: Experimental don't use in any kind of production environment."
echo "Make sure that you have downloaded and extracted the"
echo "Data Server Driver Package (dsdriver) to /vagrant/ibm/dsdriver"
echo "For vagrant you need to chose IBM Data Server Driver Package (Linux AMD64 and Intel EM64T)"
echo "Are the drivers downloaded and extracted?"
select yn in "Yes" "No"
do
    case ${yn} in
        Yes ) break;;
        No ) exit;;
    esac
done
sudo apt-get install php-pear ksh zip -y
sudo mkdir /opt/ibm -p
sudo cp -r /vagrant/ibm/dsdriver /opt/ibm/dsdriver
sudo chmod 755 /opt/ibm/dsdriver/installDSDriver
sudo /opt/ibm/dsdriver/installDSDriver || true
export IBM_DB2_HOME=/opt/ibm/dsdriver
echo "; configuration for php db2 module" | sudo tee /etc/php5/conf.d/db2.ini
sudo pear config-set php_ini /etc/php5/conf.d/db2.ini
sudo pecl config-set php_ini /etc/php5/conf.d/db2.ini
printf "/opt/ibm/dsdriver" | sudo pecl install ibm_db2
sudo service apache2 restart
echo "end of install script"
echo "run php db2test.php to test your connection"