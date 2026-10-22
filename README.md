# Self‑hosted database with built‑in language to create internal tools with ease

Universal UI, simple code-based logic, automatic actions, access rights, logging, API and lots of other stuff 👍

For rapid construction of business applications 💪 👀

On your server, easy to learn and scalable with business growth 🎉

**— Totum** — a hybrid of database and spreadsheet.

**— Ready-made frontend** — for desktops and mobile devices.

**— Small-code** — simple syntax that is easy to learn.

**— Documentation and training course** — first working table in 30 min.

**— WEB, open-source** — is installed on your own server.

**— API** —  integrate with anything.

Site — [totum.online](https://totum.online)

## Install and docs

1-Click native install on Ubuntu 26.04 `ONLY FOR CLEAR SYSTEMS` — [docs.totum.online/ubuntu](https://docs.totum.online/ubuntu)
```
sudo curl -O https://raw.githubusercontent.com/totumonline/totum-pro/pro/totum/moduls/install/totum_autoinstall.sh && sudo bash totum_autoinstall.sh
```
Avaliable lang: `EN`, `RU`, `ES`, `DE`

Documentaion — [docs.totum.online](https://docs.totum.online)

Training course — [docs.totum.online/training-course](https://docs.totum.online/training-course) 🚀

Email — `totum at totum.online`

## Interface

![main](https://github.com/totumonline/totum-mit/assets/55755565/a2dcf16f-a393-4efe-b3d7-c4b91dd7d97c)

## Using

`Data` > `Processing` > `Actions` > `Exchange` > `Reports` > `Accesses` > `Logs`

+ Production management
+ Cadastre accounting
+ Financial accounting
+ Order management
+ Stocks
+ CRM
+ Equipment inventory
+ ...

## All you need is a browser

Development and operation are in the same environment.

Tables, fields and their settings are created and managed with the mouse.

The developer can instantly hide the developer UI elements to see what the solution looks like for the user or switch to a specific user and perform an action from that user.

## Program the logic with simple codes

Totum is written in `PHP` but is internally programming with its own language — `totum code`.

**This makes development on Totum possible for non-programmers.**

In most fields, the codes are small — up to 5-10 lines.

Totum provides highlighting, searching and substitution of table and field addresses, variables and functions, and autofills parameters.

Codes are linked to fields and separated by action types:

— ones `calculate the value` similar to the formulas in Excel.

— others, follow the changes and `execute the actions`.

— the third type is responsible for `appearance` depending on conditions.


```
// Example of calculating value code

= : listSum(list: $list) + #fixed_costs

list: selectList(table: 'orders'; field: 'cost'; where: 'number' = $listNumbers)

listNumbers: selectList(table: 'orders'; field: 'number'; where: 'date' >= #first_day_months; where: 'orderStatus' = #final_status)
```


You can implement complex logic, even with the lowest programming skills. You will be able to understand it with — [the free training course](https://docs.totum.online/training-course) 👌

## Database as interface - x10 to development speed 🏃💨

Use a variety of ready-made elements:

+ Strings
+ Numbers
+ Checkboxes
+ Buttons
+ Dropdown lists
+ Trees
+ Dates
+ Files (in PRO)
+ Charts

![fields](https://github.com/totumonline/totum-mit/assets/55755565/2fb48dd6-706b-4fec-aed6-464eaeea7ece)

## And a few more details... 👀

Don't worry about concurrent access — transactions are atomic

**All actions line up in chains:** if a cancellation or error occurs while the chain is in progress — the whole chain will be cancelled.

**You can work in parallel:** if two users make a changes to the same table at the same time, the action saved by the second one will automatically restart.

## API, for any interaction

Open and customisable API allows data to be exchanged with any system for both input and output.

Exchange data with Totum via `POST` in `JSON` format.

Call to a third-party server directly from `totum-code`.

Write your own microservice on `totum-code` responding to `GET/POST` requests.

You will be able to write integrations with the website, bank, messengers and any other services working in `web`.

## Perks 📣

### Free

The Community version of Totum is distributed under an MIT-licence. Free for any type of use!

### All information can be located in the company's secure network

Totum is self hosted. You can set up any access policies for the server you manage yourself.

You also control what can be transmitted outside of this contour and what cannot.

### A copy of Totum will stay with you forever

You make a full copy of all platform scripts to your own server — you are not dependent on the availability of the developers servers, and you can modify the platform code as needed.

### Totum is scalable

In the event of dramatic success and large-scale growth of your database, the Totum solution will stand up to the load.

Even if some elements of the solution turn out to be designed for a lower load — you can modify them without rewriting the whole solution.

In addition, the database underlying Totum allows it to be clustered across multiple servers, including in large certified clouds.

## Technical basis (double elefant)

![postgres](https://totum.totum.online/fls/365_262_file.png)  ![php]( https://totum.totum.online/fls/365_261_file.png)

+ Open source code in PHP.

+ PostgreSQL database.

+ Installation on your own server.

+ Easy interaction with other software products via a simple API.

## How to try?

Site — [totum.online](https://totum.online)

Documentation — [docs.totum.online](https://docs.totum.online)

Training course — [docs.totum.online/training-course](https://docs.totum.online/training-course) 🚀

Email — `totum at totum.online`
