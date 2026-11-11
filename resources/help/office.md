# RoutePilot for the office

Every page is in the menu on the left. Each list page works the same way: the list is on the left, and clicking a row opens its details beside it (on a narrow screen, below it). Most lists have a search box and filters at the top; their buttons are in the page header.

## Dashboard (/dashboard)

Today at a glance: routes, visits done, requests waiting, money owed, stock running low and more, as widgets. Press **Customize** to add, move, resize or remove widgets (**Remove widget**). Each person's layout is their own.

## Assistant (/assistant)

This chat. It can look things up and explain how to do anything, but it can't change anything itself. **New conversation** starts fresh.

## Schedule (/schedule)

The day's routes, one column per tech, with every stop in order. Use the arrows or **Today** to change the day.

- **Skip a visit:** find the day, then use the stop's skip button (**Skip stop**). **Unskip stop** puts it back.
- **Move a stop to another tech or change the order:** drag it on the board. **Optimize order** puts a tech's stops in the shortest driving order.
- **Remove a one-off stop from a day:** **Remove from this day** on that stop.
- Visits are created from each pool's service plans for the next few weeks; **Generate** fills in any days that are missing.
- To change which day a pool is always serviced, or who services it, edit its plan on the Pools page (below), not the schedule.

## Reports (/reports)

Every finished visit: water test, chemicals added, parts used, photos, the **Note to the homeowner** and the office-only **Office note**. Open a report to correct it (**Save**). **Add** enters a visit a tech did on paper.

## Work orders (/work-orders)

Repairs and one-off jobs. **Add** a work order: **What needs doing**, **Details**, the **Pool**, **Assigned to**, **Due** date, urgent or not, and a **Price**. Open one to **Add note**, reassign it, mark it **Done**, or **Bill** it (adds the price as a charge on the customer's balance). **Mine** shows only yours.

## Inventory (/inventory)

Chemicals and repair parts. When the company counts its stock: **Update stock** with **Restocked**, **Used** or **Counted, now**; set a reorder level so low items are flagged. **Add** a new item, **Edit** it, or **Archive** what you no longer carry. If the company doesn't count stock (Company → Features), this page is just the list of chemicals (called **Chemicals** in the menu).

## Requests (/inbox)

Two tabs:

- **Requests** from customers (add a service, pause, stop, or a message). Open one and **Schedule it** to approve it (you set the price and start date), or **Decline request** with a reply. Closed ones are under the "Done" filter.
- **Leads** from the website and its chatbot. Mark one **Contacted**, **Convert to customer** (creates the customer and pool), or archive it.

## People (/people)

Customers and techs.

- **Add customer** (name, email, phone, address; you can add their first pool at the same time). **Add agent** adds a tech.
- Open a customer to edit their details, **Email settings** (visit reports, reminders, news), invite them to the customer portal, or see their pools and balance.
- **Archive** a customer who has left: it stops their plans. The **Archived** filter lists them; open one to **Restore** them, **Remove personal details**, or **Delete permanently**.
- **Email people** sends an email to a group of customers.
- Open a tech to edit them, turn Agent Plus on or off (lets a tech rearrange their own route), or deactivate them.

## Pools (/pools)

Every pool, with its latest water test.

- **Add pool** (or **New pool**): the customer, name, type, volume, sanitizer, filter, service address, **Gate code** and **Access notes**.
- **Service plans** on a pool are what put it on the schedule. **Add plan**: the service, how often, the day, the tech, the price, and the start date. Open a plan to change any of those, **Pause** or **Resume** it, or give it an end date to stop it.
- **Water targets** (**Save targets**) set this pool's own healthy ranges; doses and warnings follow them.
- **Add equipment** records the pump, filter, heater and so on (make, model, serial, warranty).
- **Also email reports to** sends this pool's visit reports to extra people.

## Services (/services)

The company's catalog of services (weekly cleaning, green-to-clean, filter clean…). **Add service** from a template (**Start from a template**) or a **Blank service**: name, description, price, usual schedule, what's included, and whether customers can ask for it from their portal. **Edit** to change one. Changing a service's price doesn't change existing plans: each plan keeps its own price (edit it on the Pools page).

## Balances (/balances)

Only when the company bills through RoutePilot (Company → Features). Who owes what, and every invoice. Open a customer to **Add charge** or **Record a payment**, **Download** an invoice as a PDF, or **Void this invoice**. Invoices are made automatically on the 1st of the month and emailed. **Export invoices** downloads them.

## Insights (/insights)

Revenue over the last 12 months, completed visits over the last 12 weeks, money owed by how overdue it is, the top techs this month, and recent website leads.

## Company (/company)

Company settings, in tabs: **Profile** (name, logo, brand color, time zone), **Business address**, **Email** (send customer email from your own mail server: **Your own mail server (SMTP)**), **Features** (switch parts of RoutePilot on or off: customer billing, stock counting, the website, the assistant, showing prices to customers) and **Payments** (connect Stripe so customers can pay by card; shown when customer billing is on).

## Subscription (/billing)

The company's own RoutePilot plan: what it costs, the trial, and **Manage billing** to change the card or see RoutePilot invoices. Pools over the plan's included number are billed at renewal.

## Landing page (/company/landing)

The company's public website: an editor with a live preview. Turn sections on or off, reorder them, and edit the text, images, services shown, FAQ and the chatbot. Only when the website is switched on (Company → Features).

## Your own settings

Click your name at the bottom of the menu:

- **Profile** (name, email, photo), **Password**, **Two-factor** (a code from an authenticator app at sign-in), **Appearance** (light or dark, **Big view** for larger text) and **Emails**.
- **Notifications** (/notifications): what you're told about, and pop-ups or phone alerts.
