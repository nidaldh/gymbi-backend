create database if not exists `gymBi`;
use gymBi;

create table banks
(
    id      int unsigned auto_increment
        primary key,
    name_ar varchar(255) not null
) collate = utf8mb4_unicode_ci;

create table cache
(
    `key`      varchar(255) not null
        primary key,
    value      mediumtext   not null,
    expiration int          not null
) collate = utf8mb4_unicode_ci;

create table cache_locks
(
    `key`      varchar(255) not null
        primary key,
    owner      varchar(255) not null,
    expiration int          not null
) collate = utf8mb4_unicode_ci;

create table failed_jobs
(
    id         bigint unsigned auto_increment
        primary key,
    uuid       varchar(255)                        not null,
    connection text                                not null,
    queue      text                                not null,
    payload    longtext                            not null,
    exception  longtext                            not null,
    failed_at  timestamp default CURRENT_TIMESTAMP not null,
    constraint failed_jobs_uuid_unique
        unique (uuid)
) collate = utf8mb4_unicode_ci;

create table job_batches
(
    id             varchar(255) not null
        primary key,
    name           varchar(255) not null,
    total_jobs     int          not null,
    pending_jobs   int          not null,
    failed_jobs    int          not null,
    failed_job_ids longtext     not null,
    options        mediumtext   null,
    cancelled_at   int          null,
    created_at     int          not null,
    finished_at    int          null
) collate = utf8mb4_unicode_ci;

create table jobs
(
    id           bigint unsigned auto_increment
        primary key,
    queue        varchar(255)     not null,
    payload      longtext         not null,
    attempts     tinyint unsigned not null,
    reserved_at  int unsigned     null,
    available_at int unsigned     not null,
    created_at   int unsigned     not null
) collate = utf8mb4_unicode_ci;

create index jobs_queue_index
    on jobs (queue);

create table migrations
(
    id        int unsigned auto_increment
        primary key,
    migration varchar(255) not null,
    batch     int          not null
) collate = utf8mb4_unicode_ci;

create table otps
(
    id            bigint unsigned auto_increment
        primary key,
    mobile_number varchar(255)                        not null,
    otp           varchar(255)                        not null,
    created_at    timestamp default CURRENT_TIMESTAMP not null
) collate = utf8mb4_unicode_ci;

create table password_reset_tokens
(
    mobile_number varchar(255) not null
        primary key,
    token         varchar(255) not null,
    created_at    timestamp    null
) collate = utf8mb4_unicode_ci;

create table personal_access_tokens
(
    id             bigint unsigned auto_increment
        primary key,
    tokenable_type varchar(255)    not null,
    tokenable_id   bigint unsigned not null,
    name           varchar(255)    not null,
    token          varchar(64)     not null,
    abilities      text            null,
    last_used_at   timestamp       null,
    expires_at     timestamp       null,
    created_at     timestamp       null,
    updated_at     timestamp       null,
    constraint personal_access_tokens_token_unique
        unique (token)
) collate = utf8mb4_unicode_ci;

create index personal_access_tokens_tokenable_type_tokenable_id_index
    on personal_access_tokens (tokenable_type, tokenable_id);

create table sessions
(
    id            varchar(255)    not null
        primary key,
    user_id       bigint unsigned null,
    ip_address    varchar(45)     null,
    user_agent    text            null,
    payload       longtext        not null,
    last_activity int             not null
) collate = utf8mb4_unicode_ci;

create index sessions_last_activity_index
    on sessions (last_activity);

create index sessions_user_id_index
    on sessions (user_id);

create table gyms
(
    id                 bigint unsigned auto_increment primary key,
    name               varchar(255) not null,
    product_attributes json         null,
    user_id            varchar(255) not null,
    created_at         timestamp default CURRENT_TIMESTAMP,
    updated_at         timestamp    null
) collate = utf8mb4_unicode_ci;

create table members
(
    id            bigint unsigned auto_increment primary key,
    gym_id        bigint unsigned not null,
    name          varchar(255)    not null,
    mobile        varchar(255)    not null,
    date_of_birth date,
    gender        enum ('male','female'),
    created_at    timestamp       null,
    updated_at    timestamp       null,
    constraint members_gym_id_foreign
        foreign key (gym_id) references gyms (id)
            on delete cascade
) collate = utf8mb4_unicode_ci;


# Subscription_Types
create table subscription_types
(
    id          bigint unsigned auto_increment primary key,
    name        varchar(255)    not null,
    description text            null,
    price       decimal(10, 2)  not null,
    duration    int             not null,
    gym_id      bigint unsigned not null,
    created_at  timestamp       null,
    updated_at  timestamp       null,
    constraint subscription_types_gym_id_foreign
        foreign key (gym_id) references gyms (id)
            on delete cascade
) collate = utf8mb4_unicode_ci;

create table subscriptions
(
    id                bigint unsigned auto_increment primary key,
    member_id         bigint unsigned not null,
    subscription_type bigint unsigned not null,
    start_date        date            not null,
    end_date          date            not null,
    price             decimal(10, 2)  not null,
    paid_amount       decimal(10, 2)  not null,
    unpaid_amount     decimal(10, 2)  not null,
    created_at        timestamp       null,
    updated_at        timestamp       null,
    constraint subscriptions_member_id_foreign
        foreign key (member_id) references members (id)
            on delete cascade,
    constraint subscriptions_subscription_type_foreign
        foreign key (subscription_type) references subscription_types (id)
            on delete cascade
) collate = utf8mb4_unicode_ci;

create table check_receivable
(
    id           bigint unsigned auto_increment
        primary key,
    check_number varchar(255)                                                                 not null,
    bank_id      int unsigned                                                                 not null,
    issuer_name  varchar(255)                                                                 null,
    amount       decimal(10, 2)                                                               not null,
    status       enum ('pending', 'cleared', 'bounced', 'canceled') default 'pending'         not null,
    due_date     date                                                                         null,
    member_id    bigint unsigned                                                              null,
    gym_id       bigint unsigned                                                              null,
    created_at   timestamp                                          default CURRENT_TIMESTAMP null,
    updated_at   timestamp                                          default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    constraint fk_check_receivables_bank_id
        foreign key (bank_id) references banks (id)
            on delete cascade,
    constraint fk_check_receivables_customer_id
        foreign key (member_id) references members (id)
            on delete cascade,
    constraint fk_check_receivables_gym_id
        foreign key (gym_id) references gyms (id)
            on delete set null
);

create table expenses
(
    id            int auto_increment
        primary key,
    name          varchar(255)                             not null,
    category      varchar(255)                             not null,
    date          date                                     not null,
    total         decimal(10, 2)                           not null,
    unpaid_amount decimal(10, 2) default 0.00              null,
    gym_id        bigint unsigned                          not null,
    created_at    timestamp      default CURRENT_TIMESTAMP null,
    updated_at    timestamp      default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    paid_amount   decimal(10, 2) default 0.00              not null,
    constraint fk_expenses_gym_id
        foreign key (gym_id) references gyms (id)
);

create table expense_transactions
(
    id          int auto_increment
        primary key,
    expense_id  int            not null,
    amount      decimal(10, 2) not null,
    date        date           not null,
    description text           null,
    constraint fk_expense_transactions_expense_id
        foreign key (expense_id) references expenses (id)
);

create table orders
(
    id            bigint unsigned auto_increment
        primary key,
    gym_id        bigint unsigned             not null,
    totalPrice    decimal(10, 2)              not null,
    unpaid_amount decimal(10, 2) default 0.00 null,
    totalCost     decimal(10, 2) default 0.00 null,
    totalDiscount decimal(10, 2)              null,
    customerId    bigint unsigned             null,
    paidAmount    decimal(10, 2)              null,
    created_at    timestamp                   null,
    updated_at    timestamp                   null,
    constraint orders_customerid_foreign
        foreign key (customerId) references gyms (id)
            on delete set null,
    constraint orders_gym_id_foreign
        foreign key (gym_id) references gyms (id)
            on delete cascade
) collate = utf8mb4_unicode_ci;

create table order_products
(
    id         bigint unsigned auto_increment primary key,
    order_id   bigint unsigned not null,
    productId  varchar(255)    not null,
    name       varchar(255)    not null,
    quantity   int             not null,
    price      decimal(10, 2)  not null,
    costPrice  decimal(10, 2)  not null,
    created_at timestamp       null,
    updated_at timestamp       null,
    constraint order_products_order_id_foreign
        foreign key (order_id) references orders (id)
            on delete cascade
) collate = utf8mb4_unicode_ci;

create table users
(
    id                        bigint unsigned auto_increment primary key,
    name                      varchar(255)                               not null,
    mobile_number             varchar(20)                                not null,
    user_type                 enum ('admin', 'employee') default 'admin' not null,
    mobile_number_verified_at timestamp                                  null,
    password                  varchar(255)                               not null,
    remember_token            varchar(100)                               null,
    created_at                timestamp                                  null,
    updated_at                timestamp                                  null,
    gym_id                    bigint unsigned                            null,
    constraint users_mobile_number_unique
        unique (mobile_number),
    constraint users_gym_id_foreign
        foreign key (gym_id) references gyms (id) on delete set null
) collate = utf8mb4_unicode_ci;

create table product_histories
(
    id          bigint unsigned auto_increment primary key,
    user_id     bigint unsigned                                                                                                          not null,
    product_id  varchar(255)                                                                                                             not null,
    gym_id      bigint unsigned                                                                                                          not null,
    description text                                                                                                                     null,
    type        enum ('insert', 'update', 'purchase', 'purchase_update', 'purchase_remove', 'purchase_delete') default 'update'          null,
    created_at  timestamp                                                                                      default CURRENT_TIMESTAMP null,
    constraint fk_product_histories_gym_id
        foreign key (gym_id) references gyms (id)
            on delete cascade,
    constraint fk_product_histories_user_id
        foreign key (user_id) references users (id)
            on delete cascade
);

create table vendors
(
    id         int auto_increment
        primary key,
    gym_id     bigint unsigned                          not null,
    name       varchar(255)                             not null,
    phone      varchar(20)                              not null,
    debt       decimal(10, 2) default 0.00              null,
    created_at timestamp      default CURRENT_TIMESTAMP null,
    updated_at timestamp      default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    constraint fk_vendors_gym_id
        foreign key (gym_id) references gyms (id)
            on update cascade on delete cascade
);

create table check_payable
(
    id           bigint unsigned auto_increment
        primary key,
    check_number varchar(255)                                                                 not null,
    bank_id      int unsigned                                                                 not null,
    issuer_name  varchar(255)                                                                 null,
    amount       decimal(10, 2)                                                               not null,
    status       enum ('pending', 'cleared', 'bounced', 'canceled') default 'pending'         not null,
    due_date     date                                                                         not null,
    expense_id   int                                                                          null,
    vendor_id    int                                                                          null,
    gym_id       bigint unsigned                                                              null,
    created_at   timestamp                                          default CURRENT_TIMESTAMP null,
    updated_at   timestamp                                          default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    constraint fk_check_payables_bank_id
        foreign key (bank_id) references banks (id)
            on delete cascade,
    constraint fk_check_payables_check_id
        foreign key (expense_id) references expenses (id)
            on delete cascade,
    constraint fk_check_payables_gym_id
        foreign key (gym_id) references gyms (id)
            on delete set null,
    constraint fk_check_payables_vendor_id
        foreign key (vendor_id) references vendors (id)
            on update cascade on delete set null
);

create table cash_transactions
(
    id                  bigint unsigned auto_increment primary key,
    member_id           bigint unsigned                     null,
    expense_id          int                                 null,
    vendor_id           int                                 null,
    order_id            bigint unsigned                     null,
    subscription_id     bigint unsigned                     null,
    check_receivable_id bigint unsigned                     null,
    check_payable_id    bigint unsigned                     null,
    amount              decimal(10, 2)                      not null,
    gym_id              bigint unsigned                     null,
    notes               text                                null,
    created_at          timestamp default CURRENT_TIMESTAMP null,
    updated_at          timestamp default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    constraint fk_cash
        foreign key (member_id) references members (id)
            on delete cascade,
    constraint fk_cash_check_payable_id
        foreign key (check_payable_id) references check_payable (id)
            on update cascade on delete set null,
    constraint fk_cash_check_receivable_id
        foreign key (check_receivable_id) references check_receivable (id)
            on update cascade on delete set null,
    constraint fk_cash_expense
        foreign key (expense_id) references expenses (id)
            on delete cascade,
    constraint fk_cash_order_id
        foreign key (order_id) references orders (id)
            on update cascade on delete set null,
    constraint fk_cash_store
        foreign key (gym_id) references gyms (id)
            on delete set null,
    constraint fk_cash_vendor_id
        foreign key (vendor_id) references vendors (id)
            on update cascade on delete set null,
    constraint fk_cash_subscription_id
        foreign key (subscription_id) references subscriptions (id)
            on update cascade on delete set null
);

create table purchases
(
    id            int auto_increment
        primary key,
    vendor_id     int                                      not null,
    total         decimal(10, 2)                           not null,
    sub_total     decimal(10, 2) default 0.00              null,
    discount      decimal(10, 2) default 0.00              null,
    unpaid_amount decimal(10, 2)                           not null,
    notes         text                                     null,
    date          date                                     not null,
    gym_id        bigint unsigned                          not null,
    created_at    timestamp      default CURRENT_TIMESTAMP null,
    updated_at    timestamp      default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    constraint purchases_ibfk_1
        foreign key (vendor_id) references vendors (id),
    constraint purchases_ibfk_2
        foreign key (gym_id) references gyms (id)
);

create table purchase_order_products
(
    id                bigint unsigned auto_increment primary key,
    purchase_order_id int                                 not null,
    product_id        varchar(255)                        not null,
    product_name      varchar(255)                        not null,
    quantity          decimal(10, 2)                      not null,
    price             decimal(10, 2)                      not null,
    created_at        timestamp default CURRENT_TIMESTAMP null,
    updated_at        timestamp default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    constraint fk_purchase_order_products_purchase_order_id
        foreign key (purchase_order_id) references purchases (id)
            on delete cascade
);

create index gym_id
    on purchases (gym_id);

create index vendor_id
    on purchases (vendor_id);


# add column to store table to enable/disable features
# checks, vendors, expenses, customers, cash_transactions
ALTER TABLE gyms
    ADD COLUMN enable_checks BOOLEAN DEFAULT 0 AFTER product_attributes,
    ADD COLUMN enable_vendors BOOLEAN DEFAULT 0 AFTER enable_checks,
    ADD COLUMN enable_expenses BOOLEAN DEFAULT 0 AFTER enable_vendors,
    ADD COLUMN enable_cash_transactions BOOLEAN DEFAULT 0 AFTER enable_customers;

# add enable_product_attributes column to store table
ALTER TABLE gyms
    ADD COLUMN enable_product_attributes BOOLEAN DEFAULT 0 AFTER enable_cash_transactions;

ALTER TABLE gyms
    ADD COLUMN enable_products BOOLEAN DEFAULT 0 AFTER enable_cash_transactions;

# add gym id to subscriptions table
ALTER TABLE subscriptions
    ADD COLUMN gym_id BIGINT UNSIGNED NOT NULL AFTER id;

ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_gym_id FOREIGN KEY (gym_id) REFERENCES gyms (id) ON DELETE CASCADE;

alter table cash_transactions
    drop foreign key fk_cash;

alter table cash_transactions
    add constraint fk_cash_member_id
        foreign key (customer_id) references gymbi.members (id)
            on delete cascade;
