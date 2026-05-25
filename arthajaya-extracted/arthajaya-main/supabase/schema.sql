-- =========================================================
-- ARTHAJAYA - Skema Database Supabase (PostgreSQL)
-- =========================================================
-- Jalankan file ini lebih dulu, baru kemudian dummy_data.sql.
-- Semua tabel memiliki Row Level Security (RLS) aktif.
-- =========================================================

-- ---------- EXTENSIONS ----------
-- Supabase sudah menyediakan schema 'extensions' khusus untuk modul tambahan
create extension if not exists pgcrypto with schema extensions;

-- =========================================================
-- 1. ENUM TYPES
-- =========================================================
do $$ begin
  create type user_role as enum ('admin', 'bendahara', 'anggota');
exception when duplicate_object then null; end $$;

do $$ begin
  create type member_status as enum ('active', 'inactive');
exception when duplicate_object then null; end $$;

do $$ begin
  create type saving_type as enum ('pokok', 'wajib', 'sukarela');
exception when duplicate_object then null; end $$;

do $$ begin
  create type saving_tx_type as enum ('deposit', 'withdrawal');
exception when duplicate_object then null; end $$;

do $$ begin
  create type loan_status as enum ('pending', 'active', 'paid', 'rejected');
exception when duplicate_object then null; end $$;

do $$ begin
  create type installment_status as enum ('unpaid', 'paid', 'late');
exception when duplicate_object then null; end $$;

-- =========================================================
-- 2. TABLES
-- =========================================================

-- 2.1 PROFILES (1:1 dengan auth.users)
create table if not exists public.profiles (
  id          uuid primary key references auth.users(id) on delete cascade,
  role        user_role not null default 'anggota',
  full_name   text       not null,
  phone       text,
  address     text,
  created_at  timestamptz not null default now()
);

-- 2.2 MEMBERS
create table if not exists public.members (
  id            uuid primary key default gen_random_uuid(),
  user_id       uuid not null references public.profiles(id) on delete cascade,
  member_number text not null unique,
  join_date     date not null default current_date,
  status        member_status not null default 'active',
  created_at    timestamptz not null default now(),
  constraint members_user_unique unique (user_id)
);
create index if not exists idx_members_user_id on public.members(user_id);

-- 2.3 SAVINGS
create table if not exists public.savings (
  id                uuid primary key default gen_random_uuid(),
  member_id         uuid not null references public.members(id) on delete cascade,
  type              saving_type    not null,
  amount            numeric(14,2)  not null check (amount > 0),
  transaction_type  saving_tx_type not null,
  description       text,
  created_at        timestamptz not null default now()
);
create index if not exists idx_savings_member on public.savings(member_id);
create index if not exists idx_savings_created_at on public.savings(created_at desc);

-- 2.4 LOANS
create table if not exists public.loans (
  id             uuid primary key default gen_random_uuid(),
  member_id      uuid not null references public.members(id) on delete cascade,
  amount         numeric(14,2) not null check (amount > 0),
  interest_rate  numeric(5,2)  not null default 1.5,  -- persen per bulan
  tenor          integer       not null check (tenor between 1 and 60),
  status         loan_status   not null default 'pending',
  approved_at    timestamptz,
  created_at     timestamptz   not null default now()
);
create index if not exists idx_loans_member on public.loans(member_id);
create index if not exists idx_loans_status on public.loans(status);

-- 2.5 INSTALLMENTS
create table if not exists public.installments (
  id                  uuid primary key default gen_random_uuid(),
  loan_id             uuid not null references public.loans(id) on delete cascade,
  installment_number  integer not null,
  amount              numeric(14,2) not null check (amount > 0),
  penalty             numeric(14,2) not null default 0,
  paid_at             timestamptz,
  status              installment_status not null default 'unpaid',
  created_at          timestamptz not null default now(),
  unique (loan_id, installment_number)
);
create index if not exists idx_installments_loan on public.installments(loan_id);

-- =========================================================
-- 3. FUNGSI BANTUAN (HELPERS)
-- =========================================================

-- 3.1 Dapatkan role pengguna saat ini (dipakai oleh RLS)
create or replace function public.current_user_role()
returns user_role
language sql stable security definer
set search_path = public
as $$
  select role from public.profiles where id = auth.uid();
$$;

-- 3.2 Generate nomor anggota berikutnya (AJ-YYYY-0001)
create or replace function public.next_member_number()
returns text
language plpgsql
as $$
declare
  seq int;
  prefix text := 'AJ-' || to_char(now(), 'YYYY') || '-';
begin
  select coalesce(max( (substring(member_number from '\d+$'))::int ), 0) + 1
    into seq
  from public.members
  where member_number like prefix || '%';

  return prefix || lpad(seq::text, 4, '0');
end $$;

-- 3.3 Trigger: saat user baru dibuat di auth.users → buat profile + member otomatis
create or replace function public.handle_new_user()
returns trigger
language plpgsql security definer
set search_path = public
as $$
declare
  v_role     user_role := coalesce((new.raw_user_meta_data ->> 'role')::user_role, 'anggota');
  v_fullname text       := coalesce(new.raw_user_meta_data ->> 'full_name', new.email);
  v_phone    text       := new.raw_user_meta_data ->> 'phone';
  v_address  text       := new.raw_user_meta_data ->> 'address';
begin
  insert into public.profiles (id, role, full_name, phone, address)
  values (new.id, v_role, v_fullname, v_phone, v_address)
  on conflict (id) do nothing;

  -- Hanya anggota yang otomatis mendapat kartu member
  if v_role = 'anggota' then
    insert into public.members (user_id, member_number)
    values (new.id, public.next_member_number())
    on conflict (user_id) do nothing;
  end if;

  return new;
end $$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

-- 3.4 Saat pinjaman disetujui (status → active), generate jadwal angsuran otomatis
create or replace function public.generate_installments()
returns trigger
language plpgsql
as $$
declare
  i int;
  monthly numeric(14,2);
begin
  if new.status = 'active' and (old.status is distinct from 'active') then
    -- cicilan = (pokok * (1 + bunga_total)) / tenor; bunga flat per bulan
    monthly := round((new.amount * (1 + (new.interest_rate / 100.0) * new.tenor)) / new.tenor, 2);

    for i in 1 .. new.tenor loop
      insert into public.installments (loan_id, installment_number, amount)
      values (new.id, i, monthly)
      on conflict do nothing;
    end loop;
  end if;
  return new;
end $$;

drop trigger if exists trg_generate_installments on public.loans;
create trigger trg_generate_installments
  after update on public.loans
  for each row execute function public.generate_installments();

-- =========================================================
-- 4. ROW LEVEL SECURITY
-- =========================================================
alter table public.profiles     enable row level security;
alter table public.members      enable row level security;
alter table public.savings      enable row level security;
alter table public.loans        enable row level security;
alter table public.installments enable row level security;

-- ---------- PROFILES ----------
drop policy if exists "profiles_self_read"       on public.profiles;
drop policy if exists "profiles_staff_read_all"  on public.profiles;
drop policy if exists "profiles_self_update"     on public.profiles;
drop policy if exists "profiles_admin_all"       on public.profiles;

create policy "profiles_self_read" on public.profiles
  for select using (auth.uid() = id);

create policy "profiles_staff_read_all" on public.profiles
  for select using (public.current_user_role() in ('admin','bendahara'));

create policy "profiles_self_update" on public.profiles
  for update using (auth.uid() = id);

create policy "profiles_admin_all" on public.profiles
  for all using (public.current_user_role() = 'admin')
  with check (public.current_user_role() = 'admin');

-- ---------- MEMBERS ----------
drop policy if exists "members_self_read"      on public.members;
drop policy if exists "members_staff_read_all" on public.members;
drop policy if exists "members_staff_write"    on public.members;

create policy "members_self_read" on public.members
  for select using (auth.uid() = user_id);

create policy "members_staff_read_all" on public.members
  for select using (public.current_user_role() in ('admin','bendahara'));

create policy "members_staff_write" on public.members
  for all using (public.current_user_role() in ('admin','bendahara'))
  with check (public.current_user_role() in ('admin','bendahara'));

-- ---------- SAVINGS ----------
drop policy if exists "savings_self_read"      on public.savings;
drop policy if exists "savings_staff_read_all" on public.savings;
drop policy if exists "savings_self_insert"    on public.savings;
drop policy if exists "savings_staff_write"    on public.savings;

create policy "savings_self_read" on public.savings
  for select using (
    exists (select 1 from public.members m where m.id = savings.member_id and m.user_id = auth.uid())
  );

create policy "savings_staff_read_all" on public.savings
  for select using (public.current_user_role() in ('admin','bendahara'));

-- Anggota hanya boleh setor (deposit) ke akun sendiri
create policy "savings_self_insert" on public.savings
  for insert with check (
    transaction_type = 'deposit'
    and exists (select 1 from public.members m where m.id = member_id and m.user_id = auth.uid())
  );

create policy "savings_staff_write" on public.savings
  for all using (public.current_user_role() in ('admin','bendahara'))
  with check (public.current_user_role() in ('admin','bendahara'));

-- ---------- LOANS ----------
drop policy if exists "loans_self_read"      on public.loans;
drop policy if exists "loans_staff_read_all" on public.loans;
drop policy if exists "loans_self_insert"    on public.loans;
drop policy if exists "loans_staff_write"    on public.loans;

create policy "loans_self_read" on public.loans
  for select using (
    exists (select 1 from public.members m where m.id = loans.member_id and m.user_id = auth.uid())
  );

create policy "loans_staff_read_all" on public.loans
  for select using (public.current_user_role() in ('admin','bendahara'));

-- Anggota hanya boleh mengajukan (status = pending) untuk dirinya sendiri
create policy "loans_self_insert" on public.loans
  for insert with check (
    status = 'pending'
    and exists (select 1 from public.members m where m.id = member_id and m.user_id = auth.uid())
  );

create policy "loans_staff_write" on public.loans
  for all using (public.current_user_role() in ('admin','bendahara'))
  with check (public.current_user_role() in ('admin','bendahara'));

-- ---------- INSTALLMENTS ----------
drop policy if exists "installments_self_read"   on public.installments;
drop policy if exists "installments_staff_all"   on public.installments;

create policy "installments_self_read" on public.installments
  for select using (
    exists (
      select 1
      from public.loans l
      join public.members m on m.id = l.member_id
      where l.id = installments.loan_id and m.user_id = auth.uid()
    )
  );

create policy "installments_staff_all" on public.installments
  for all using (public.current_user_role() in ('admin','bendahara'))
  with check (public.current_user_role() in ('admin','bendahara'));

-- =========================================================
-- Selesai. Jalankan sekarang dummy_data.sql untuk seeding.
-- =========================================================
