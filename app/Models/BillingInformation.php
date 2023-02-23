<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInformation extends Model
{
    //
    protected $table = 'billing_information';
    protected $guarded = [];

    const DEFAULT = ' 
    c.id,
    c.active_field_id,
    c.name,
    IF(bi.tank_automatic_billing_start_date, 
        DATE_ADD(bi.tank_contract_signed_date,
        INTERVAL IF(bi.tank_free_years IS NOT NULL, bi.tank_free_years, 0) YEAR), 
        bi.tank_billing_start_date)
    AS tank_billing_start_date,
    IF(bi.non_tank_automatic_billing_start_date, 
        DATE_ADD(bi.non_tank_contract_signed_date, 
        INTERVAL IF(bi.non_tank_free_years IS NOT NULL, bi.non_tank_free_years, 0) YEAR), 
        bi.non_tank_billing_start_date) 
    AS non_tank_billing_start_date,
    bi.last_billed_date,
    bi.is_discountable,
    bi.tank_contract_signed_date as tank_contract_signed_date,
    bi.non_tank_contract_signed_date as non_tank_contract_signed_date,
    COUNT(v.id) AS total_number_of_vessels,
    (IFNULL(IF(bi.is_discountable, If(bi.manual_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_tank 
            WHERE (SELECT COUNT(vessels.id) 
            FROM vessels 
            JOIN companies ON companies.id = vessels.company_id 
            JOIN billing_information ON vessels.company_id = billing_information.company_id
            WHERE vessels.company_id = c.id 
                AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                AND vessels.tanker=1 
                AND billing_information.is_discountable=1)
            BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme), 0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2), 
        ROUND((100-bi.manual_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2)), 
        ROUND(SUM(IF(v.tanker = 1,
        bi.tank_annual_retainer_fee,
        0)), 2)), 0) + IFNULL(IF(bi.is_discountable, If(bi.manual_non_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_non_tank 
            WHERE (SELECT COUNT(vessels.id) 
            FROM vessels 
            JOIN companies ON companies.id = vessels.company_id 
            JOIN billing_information ON vessels.company_id = billing_information.company_id
            WHERE vessels.company_id = c.id 
                AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                AND vessels.tanker=0 
                AND billing_information.is_discountable=1)
            BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme),0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2), 
        ROUND((100-bi.manual_non_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2)), 
        ROUND(SUM(IF(v.tanker = 0,
        bi.non_tank_annual_retainer_fee,
        0)), 2)), 0)) 
    AS overall_total_fee,
    bi.tank_contract_no,
    (SELECT users.username FROM users 
        JOIN account_managers ON account_managers.user_id = users.id
        JOIN account_manager_companies ON account_manager_companies.account_manager_id = account_managers.id
        JOIN companies ON companies.id = account_manager_companies.company_id
        WHERE account_manager_companies.company_id = c.id) AS account_manager_name,
    (SELECT company_addresses.country FROM company_addresses 
        JOIN companies ON company_addresses.company_id = companies.id
        WHERE company_addresses.address_type_id = 3 AND company_addresses.company_id = c.id LIMIT 1) AS country,
    (SELECT countries.region_code FROM countries 
        JOIN company_addresses ON countries.code = company_addresses.country
        JOIN companies ON company_addresses.company_id = companies.id
        WHERE company_addresses.address_type_id = 3 AND company_addresses.company_id = c.id LIMIT 1) AS region,
    (SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) 
        AS number_of_tank,
    (bi.tank_annual_retainer_fee) AS gross_tank_fee,
    ROUND(SUM(IF(v.tanker = 1,
            bi.tank_annual_retainer_fee,
            0)),
        2) AS gross_tank_total,
    (SELECT discount FROM discount_tank 
        WHERE (SELECT COUNT(vessels.id) 
        FROM vessels 
        JOIN companies ON companies.id = vessels.company_id 
        JOIN billing_information ON vessels.company_id = billing_information.company_id
        WHERE vessels.company_id = c.id 
            AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
            AND vessels.tanker=1 
            AND billing_information.is_discountable=1)
        BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme) 
    AS auto_tank_discount,
    bi.manual_tank_discount,
    ROUND((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee,2) - if(bi.manual_tank_discount IS NULL, 
        ROUND((100-(SELECT discount FROM discount_tank 
                WHERE (SELECT COUNT(vessels.id) 
                FROM vessels 
                JOIN companies ON companies.id = vessels.company_id 
                JOIN billing_information ON vessels.company_id = billing_information.company_id
                WHERE vessels.company_id = c.id 
                    AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                    AND vessels.tanker=1 
                    AND billing_information.is_discountable=1)
                BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme))/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2), 
        ROUND((100-bi.manual_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2)) 
    AS tank_discount_value,
    IF(bi.is_discountable, If(bi.manual_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_tank 
            WHERE (SELECT COUNT(vessels.id) 
            FROM vessels 
            JOIN companies ON companies.id = vessels.company_id 
            JOIN billing_information ON vessels.company_id = billing_information.company_id
            WHERE vessels.company_id = c.id 
                AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                AND vessels.tanker=1 
                AND billing_information.is_discountable=1)
            BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme), 0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2), 
        ROUND((100-bi.manual_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2)), 
        ROUND(SUM(IF(v.tanker = 1,
        bi.tank_annual_retainer_fee,
        0)), 2)) 
    AS tank_net_total,
    bi.non_tank_contract_no,
    (SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR vessels.active_field_id=5)) 
        AS number_of_non_tank,
    (bi.non_tank_annual_retainer_fee) AS gross_non_tank_fee,
    ROUND(SUM(IF(v.tanker = 0,
                bi.non_tank_annual_retainer_fee,
                0)), 2) AS gross_non_tank_total,
    (SELECT discount FROM discount_non_tank 
        WHERE (SELECT COUNT(vessels.id) 
        FROM vessels 
        JOIN companies ON companies.id = vessels.company_id 
        JOIN billing_information ON vessels.company_id = billing_information.company_id
        WHERE vessels.company_id = c.id 
            AND (vessels.active_field_id=2 OR vessels.active_field_id=3) 
            AND vessels.tanker=0
            AND billing_information.is_discountable=1)
        BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme) 
    AS auto_non_tank_discount,
    bi.manual_non_tank_discount,
    ROUND((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee,2) - if(bi.manual_non_tank_discount IS NULL, 
        ROUND((100-(SELECT discount FROM discount_non_tank 
                WHERE (SELECT COUNT(vessels.id) 
                FROM vessels 
                JOIN companies ON companies.id = vessels.company_id 
                JOIN billing_information ON vessels.company_id = billing_information.company_id
                WHERE vessels.company_id = c.id 
                    AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                    AND vessels.tanker=0 
                    AND billing_information.is_discountable=1)
                BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme))/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2), 
        ROUND((100-bi.manual_non_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
        FROM vessels 
        WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2)) 
    AS non_tank_discount_value,
    IF(bi.is_discountable, If(bi.manual_non_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_non_tank 
            WHERE (SELECT COUNT(vessels.id) 
            FROM vessels 
            JOIN companies ON companies.id = vessels.company_id 
            JOIN billing_information ON vessels.company_id = billing_information.company_id
            WHERE vessels.company_id = c.id 
                AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                AND vessels.tanker=0 
                AND billing_information.is_discountable=1)
            BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme),0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
    FROM vessels 
    WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2), 
    ROUND((100-bi.manual_non_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
    FROM vessels 
    WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2)), 
    ROUND(SUM(IF(v.tanker = 0,
        bi.non_tank_annual_retainer_fee,
        0)), 2)) 
    AS non_tank_net_total';

    const GROUP = ' 
            c.id,
            c.active_field_id,
            c.name,
            vbg.name AS billing_name,
            IF(bi.tank_automatic_billing_start_date, 
                DATE_ADD(bi.tank_contract_signed_date,
                INTERVAL IF(bi.tank_free_years IS NOT NULL, bi.tank_free_years, 0) YEAR), 
                bi.tank_billing_start_date)
            AS tank_billing_start_date,
            IF(bi.non_tank_automatic_billing_start_date, 
                DATE_ADD(bi.non_tank_contract_signed_date, 
                INTERVAL IF(bi.non_tank_free_years IS NOT NULL, bi.non_tank_free_years, 0) YEAR), 
                bi.non_tank_billing_start_date) 
            AS non_tank_billing_start_date,
            bi.last_billed_date,
            bi.is_discountable,
            bi.tank_contract_signed_date as tank_contract_signed_date,
            bi.non_tank_contract_signed_date as non_tank_contract_signed_date,
            COUNT(v.id) AS total_number_of_vessels,
            (IFNULL(IF(bi.is_discountable, If(bi.manual_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_tank 
                    WHERE (SELECT COUNT(vessels.id) 
                    FROM vessels 
                    JOIN companies ON companies.id = vessels.company_id 
                    JOIN billing_information ON vessels.company_id = billing_information.company_id
                    WHERE vessels.company_id = c.id 
                        AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                        AND vessels.tanker=1 
                        AND billing_information.is_discountable=1)
                    BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme), 0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2), 
                ROUND((100-bi.manual_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2)), 
                ROUND(SUM(IF(v.tanker = 1,
                bi.tank_annual_retainer_fee,
                0)), 2)), 0) + IFNULL(IF(bi.is_discountable, If(bi.manual_non_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_non_tank 
                    WHERE (SELECT COUNT(vessels.id) 
                    FROM vessels 
                    JOIN companies ON companies.id = vessels.company_id 
                    JOIN billing_information ON vessels.company_id = billing_information.company_id
                    WHERE vessels.company_id = c.id 
                        AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                        AND vessels.tanker=0 
                        AND billing_information.is_discountable=1)
                    BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme),0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2), 
                ROUND((100-bi.manual_non_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2)), 
                ROUND(SUM(IF(v.tanker = 0,
                bi.non_tank_annual_retainer_fee,
                0)), 2)), 0)) 
            AS overall_total_fee,
            bi.tank_contract_no,
            (SELECT users.username FROM users 
                JOIN account_managers ON account_managers.user_id = users.id
                JOIN account_manager_companies ON account_manager_companies.account_manager_id = account_managers.id
                JOIN companies ON companies.id = account_manager_companies.company_id
                WHERE account_manager_companies.company_id = c.id) AS account_manager_name,
            (SELECT company_addresses.country FROM company_addresses 
                JOIN companies ON company_addresses.company_id = companies.id
                WHERE company_addresses.address_type_id = 3 AND company_addresses.company_id = c.id LIMIT 1) AS country,
            (SELECT countries.region_code FROM countries 
                JOIN company_addresses ON countries.code = company_addresses.country
                JOIN companies ON company_addresses.company_id = companies.id
                WHERE company_addresses.address_type_id = 3 AND company_addresses.company_id = c.id LIMIT 1) AS region,
            (SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) 
                AS number_of_tank,
            (bi.tank_annual_retainer_fee) AS gross_tank_fee,
            ROUND(SUM(IF(v.tanker = 1,
                    bi.tank_annual_retainer_fee,
                    0)),
                2) AS gross_tank_total,
            (SELECT discount FROM discount_tank 
                WHERE (SELECT COUNT(vessels.id) 
                FROM vessels 
                JOIN companies ON companies.id = vessels.company_id 
                JOIN billing_information ON vessels.company_id = billing_information.company_id
                WHERE vessels.company_id = c.id 
                    AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                    AND vessels.tanker=1 
                    AND billing_information.is_discountable=1)
                BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme) 
            AS auto_tank_discount,
            bi.manual_tank_discount,
            ROUND((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee,2) - if(bi.manual_tank_discount IS NULL, 
                ROUND((100-(SELECT discount FROM discount_tank 
                        WHERE (SELECT COUNT(vessels.id) 
                        FROM vessels 
                        JOIN companies ON companies.id = vessels.company_id 
                        JOIN billing_information ON vessels.company_id = billing_information.company_id
                        WHERE vessels.company_id = c.id 
                            AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                            AND vessels.tanker=1 
                            AND billing_information.is_discountable=1)
                        BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme))/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2), 
                ROUND((100-bi.manual_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2)) 
            AS tank_discount_value,
            IF(bi.is_discountable, If(bi.manual_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_tank 
                    WHERE (SELECT COUNT(vessels.id) 
                    FROM vessels 
                    JOIN companies ON companies.id = vessels.company_id 
                    JOIN billing_information ON vessels.company_id = billing_information.company_id
                    WHERE vessels.company_id = c.id 
                        AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                        AND vessels.tanker=1 
                        AND billing_information.is_discountable=1)
                    BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme), 0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2), 
                ROUND((100-bi.manual_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 1, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.tank_annual_retainer_fee),2)), 
                ROUND(SUM(IF(v.tanker = 1,
                bi.tank_annual_retainer_fee,
                0)), 2)) 
            AS tank_net_total,
            bi.non_tank_contract_no,
            (SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) 
                AS number_of_non_tank,
            (bi.non_tank_annual_retainer_fee) AS gross_non_tank_fee,
            ROUND(SUM(IF(v.tanker = 0,
                        bi.non_tank_annual_retainer_fee,
                        0)), 2) AS gross_non_tank_total,
            (SELECT discount FROM discount_non_tank 
                WHERE (SELECT COUNT(vessels.id) 
                FROM vessels 
                JOIN companies ON companies.id = vessels.company_id 
                JOIN billing_information ON vessels.company_id = billing_information.company_id
                WHERE vessels.company_id = c.id 
                    AND (vessels.active_field_id=2 OR vessels.active_field_id=3) 
                    AND vessels.tanker=0
                    AND billing_information.is_discountable=1)
                BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme) 
            AS auto_non_tank_discount,
            bi.manual_non_tank_discount,
            ROUND((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee,2) - if(bi.manual_non_tank_discount IS NULL, 
                ROUND((100-(SELECT discount FROM discount_non_tank 
                        WHERE (SELECT COUNT(vessels.id) 
                        FROM vessels 
                        JOIN companies ON companies.id = vessels.company_id 
                        JOIN billing_information ON vessels.company_id = billing_information.company_id
                        WHERE vessels.company_id = c.id 
                            AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                            AND vessels.tanker=0 
                            AND billing_information.is_discountable=1)
                        BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme))/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2), 
                ROUND((100-bi.manual_non_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
                FROM vessels 
                WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2)) 
            AS non_tank_discount_value,
            IF(bi.is_discountable, If(bi.manual_non_tank_discount IS NULL, ROUND((100-(IFNULL((SELECT discount FROM discount_non_tank 
                    WHERE (SELECT COUNT(vessels.id) 
                    FROM vessels 
                    JOIN companies ON companies.id = vessels.company_id 
                    JOIN billing_information ON vessels.company_id = billing_information.company_id
                    WHERE vessels.company_id = c.id 
                        AND (vessels.active_field_id=2 OR vessels.active_field_id=3 OR vessels.active_field_id=5) 
                        AND vessels.tanker=0 
                        AND billing_information.is_discountable=1)
                    BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme),0)))/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
            FROM vessels 
            WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2), 
            ROUND((100-bi.manual_non_tank_discount)/100 * ((SELECT COUNT(IF(vessels.tanker = 0, 1, NULL)) AS number_of_non_tank 
            FROM vessels 
            WHERE vessels.company_id=c.id AND (active_field_id=2 OR active_field_id=3 OR active_field_id=5)) * bi.non_tank_annual_retainer_fee),2)), 
            ROUND(SUM(IF(v.tanker = 0,
                bi.non_tank_annual_retainer_fee,
                0)), 2)) 
            AS non_tank_net_total';

    const TOTAL_AMOUNT = '
        SELECT SUM(total_amount) FROM (SELECT ROUND(SUM(IF(vessels.tanker = 1, billing_information.tank_annual_retainer_fee, 0)) * 
            (1 - (IFNULL(billing_information.manual_tank_discount, IFNULL((SELECT discount_tank.discount
                FROM discount_tank
                WHERE IF(vessels.tanker = 1 AND billing_information.is_discountable = 1, COUNT(vessels.id), 0) 
                BETWEEN discount_tank.min_extreme AND discount_tank.max_extreme), 0))) / 100) + 
                (SUM(IF(vessels.tanker = 0, billing_information.non_tank_annual_retainer_fee, 0))) * (1 - 
                (IFNULL(billing_information.manual_non_tank_discount,
                    IFNULL((SELECT discount_non_tank.discount
                FROM discount_non_tank
                WHERE IF(vessels.tanker = 0 AND billing_information.is_discountable = 1, COUNT(vessels.id), 0) 
                BETWEEN discount_non_tank.min_extreme AND discount_non_tank.max_extreme), 0))) / 100), 2) AS total_amount
    ';

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
}
