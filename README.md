# ERIVE.delivery Shipping Method

## Table of Contents

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [License](#license)
- [Support](#support)

## Introduction

This module adds a custom online shipping method named ERIVE.delivery.
The creation of a shipment in Shopware automatically triggers the creation of a parcel with status announced in the ERIVE.delivery Cockpit.
The tracking url for the shipment is generated automatically.

## Requirements

- Shopware 6.4.18 or greater
- PHP 8.1 or greater

## Installation

Install using composer or manual installation.

### Type 1: Composer

- Add the composer repository url to your composer configuration https://github.com/eriveeu/delivery-shopware-extension.git
- Install the module by running `composer require erive/delivery-shopware`
- Enable the module by running `bin/console plugin:install --activate EriveDelivery`
- Flush the cache by running `bin/console cache:clear`

### Type 2: Zip file

- Download the zip file from https://github.com/eriveeu/delivery-shopware-extension/releases/latest
- Unzip the zip file in `custom/plugins/EriveDelivery`
- Enable the module by running `bin/console plugin:install --activate EriveDelivery`
- Flush the cache by running `bin/console cache:clear`


## Configuration

Configure the module under Extensions > My extensions > ERIVE.delivery > Configure

Configuration fields not mentioned are self-explanatory or shopware default.

### Basic configuration

**Whitelisted delivery methods:** Orders placed with selected delivery methods will be submitted to the ERIVE.delivery platform

**Automatically announce parcel:** Parcel needs to be ready for pickup. Pickup is scheduled

**Print label for each product:** Each product is packaged separately and needs a parcel label

### Environment configuration

**ERIVE Server Enviroment:** ERIVE.delivery offers Dev and Stage enviroments for testing purposes. Select production for live usage.

**Custom API Endpoint:** Endpoint to use if Erive Server Environment is set to custom. e.g. ERIVE on your local machine, test environment

**API Key:** Request API Key from ERIVE.delivery

## License

See LICENSE.txt for details.

## Support

ERIVE GmbH, developers@erive.eu