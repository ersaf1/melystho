-- =========================================================
-- ARTHAJAYA - Dummy Data Seeder
-- =========================================================
-- Jalankan SETELAH schema.sql selesai dieksekusi.
-- File ini membuat 3 pengguna uji (admin, bendahara, anggota)
-- beserta data simpanan, pinjaman, dan angsuran contoh.
--
-- Kredensial login (semua password: password123):
--   - admin@arthajaya.com      (role: admin)
--   - bendahara@arthajaya.com  (role: bendahara)
--   - anggota@arthajaya.com    (role: anggota)
--   - budi@arthajaya.com       (role: anggota)
--   - siti@arthajaya.com       (role: anggota)
--
-- PENTING:
--   Supabase SQL Editor berjalan sebagai service_role sehingga
--   dapat menulis langsung ke schema `auth`. Jika Anda memakai
--   koneksi biasa, jalankan file ini dari dashboard Supabase.
-- =========================================================

-- Hapus data lama (opsional, aman dipanggil berulang)
do $$
declare
  v_email text;
begin
  for v_email in
    select unnest(array[
      'admin@arthajaya.com',
      'bendahara@arthajaya.com',
      'anggota@arthajaya.com',
      'budi@arthajaya.com',
      'siti@arthajaya.com'
    ])
  loop
    delete from auth.users where email = v_email;
  end loop;
end $$;

-- =========================================================
-- 1. FUNGSI BANTUAN: buat user auth + profile + (opsional) member
-- =========================================================
-- Pastikan pgcrypto tersedia (Supabase memasangnya di schema 'extensions')
create extension if not exists pgcrypto with schema extensions;

create or replace function public.seed_user(
  p_email     text,
  p_password  text,
  p_full_name text,
  p_role      user_role,
  p_phone     text default null,
  p_address   text default null
) returns uuid
language plpgsql security definer
set search_path = public, auth, extensions
as $$
declare
  v_uid uuid := gen_random_uuid();
begin
  -- 1) auth.users
  insert into auth.users (
    id, instance_id, aud, role, email, encrypted_password,
    email_confirmed_at, raw_app_meta_data, raw_user_meta_data,
    created_at, updated_at, confirmation_token, recovery_token,
    email_change_token_new, email_change
  ) values (
    v_uid,
    '00000000-0000-0000-0000-000000000000',
    'authenticated',
    'authenticated',
    p_email,
    extensions.crypt(p_password, extensions.gen_salt('bf')),
    now(),
    jsonb_build_object('provider','email','providers', jsonb_build_array('email')),
    jsonb_build_object('full_name', p_full_name, 'phone', p_phone, 'address', p_address, 'role', p_role::text),
    now(), now(), '', '', '', ''
  );

  -- 2) auth.identities (diperlukan agar login email/password bekerja)
  insert into auth.identities (
    id, user_id, provider_id, identity_data, provider,
    last_sign_in_at, created_at, updated_at
  ) values (
    gen_random_uuid(),
    v_uid,
    v_uid::text,
    jsonb_build_object('sub', v_uid::text, 'email', p_email, 'email_verified', true),
    'email',
    now(), now(), now()
  );

  -- 3) Trigger handle_new_user() akan membuat profiles + members otomatis.
  --    Namun untuk kepastian (kalau trigger tidak aktif) kita upsert manual di bawah.
  insert into public.profiles (id, role, full_name, phone, address)
  values (v_uid, p_role, p_full_name, p_phone, p_address)
  on conflict (id) do update
    set role = excluded.role,
        full_name = excluded.full_name,
        phone = excluded.phone,
        address = excluded.address;

  if p_role = 'anggota' then
    insert into public.members (user_id, member_number)
    values (v_uid, public.next_member_number())
    on conflict (user_id) do nothing;
  end if;

  return v_uid;
end $$;

-- =========================================================
-- 2. SEED USERS
-- =========================================================
do $$
declare
  v_admin      uuid;
  v_bendahara  uuid;
  v_anggota_1  uuid;
  v_anggota_2  uuid;
  v_anggota_3  uuid;
  m1 uuid; m2 uuid; m3 uuid;
  loan_1 uuid; loan_2 uuid;
begin
  v_admin     := public.seed_user('admin@arthajaya.com',     'password123', 'Admin Arthajaya',     'admin',     '081200000001', 'Kantor Pusat Arthajaya');
  v_bendahara := public.seed_user('bendahara@arthajaya.com', 'password123', 'Bendahara Arthajaya', 'bendahara', '081200000002', 'Kantor Pusat Arthajaya');
  v_anggota_1 := public.seed_user('anggota@arthajaya.com',   'password123', 'Ahmad Wijaya',        'anggota',   '081200000003', 'Jl. Melati No. 10, Jakarta');
  v_anggota_2 := public.seed_user('budi@arthajaya.com',      'password123', 'Budi Santoso',        'anggota',   '081200000004', 'Jl. Kenanga No. 22, Bandung');
  v_anggota_3 := public.seed_user('siti@arthajaya.com',      'password123', 'Siti Rahayu',         'anggota',   '081200000005', 'Jl. Mawar No. 5, Surabaya');

  -- Ambil member id
  select id into m1 from public.members where user_id = v_anggota_1;
  select id into m2 from public.members where user_id = v_anggota_2;
  select id into m3 from public.members where user_id = v_anggota_3;

  -- =========================================================
  -- 3. SEED SAVINGS (simpanan)
  -- =========================================================
  insert into public.savings (member_id, type, amount, transaction_type, description, created_at) values
    (m1, 'pokok',    500000,  'deposit',    'Simpanan pokok pendaftaran', now() - interval '90 days'),
    (m1, 'wajib',    100000,  'deposit',    'Simpanan wajib bulan 1',     now() - interval '60 days'),
    (m1, 'wajib',    100000,  'deposit',    'Simpanan wajib bulan 2',     now() - interval '30 days'),
    (m1, 'sukarela', 750000,  'deposit',    'Tabungan liburan',           now() - interval '15 days'),
    (m1, 'sukarela', 200000,  'withdrawal', 'Penarikan darurat',          now() - interval '7  days'),

    (m2, 'pokok',    500000,  'deposit',    'Simpanan pokok pendaftaran', now() - interval '120 days'),
    (m2, 'wajib',    100000,  'deposit',    'Simpanan wajib bulan 1',     now() - interval '90 days'),
    (m2, 'wajib',    100000,  'deposit',    'Simpanan wajib bulan 2',     now() - interval '60 days'),
    (m2, 'wajib',    100000,  'deposit',    'Simpanan wajib bulan 3',     now() - interval '30 days'),
    (m2, 'sukarela', 1500000, 'deposit',    'Tabungan pendidikan anak',   now() - interval '20 days'),

    (m3, 'pokok',    500000,  'deposit',    'Simpanan pokok pendaftaran', now() - interval '45 days'),
    (m3, 'wajib',    100000,  'deposit',    'Simpanan wajib bulan 1',     now() - interval '15 days'),
    (m3, 'sukarela', 300000,  'deposit',    'Tabungan awal',              now() - interval '5  days');

  -- =========================================================
  -- 4. SEED LOANS (pinjaman)
  -- =========================================================
  -- Pinjaman aktif (sudah disetujui) untuk anggota 1
  insert into public.loans (member_id, amount, interest_rate, tenor, status, approved_at, created_at)
  values (m1, 5000000, 1.5, 12, 'active', now() - interval '60 days', now() - interval '65 days')
  returning id into loan_1;

  -- Pinjaman pending untuk anggota 2
  insert into public.loans (member_id, amount, interest_rate, tenor, status, created_at)
  values (m2, 10000000, 1.5, 24, 'pending', now() - interval '3 days')
  returning id into loan_2;

  -- Pinjaman lunas untuk anggota 3
  insert into public.loans (member_id, amount, interest_rate, tenor, status, approved_at, created_at)
  values (m3, 2000000, 1.0, 6, 'paid', now() - interval '200 days', now() - interval '210 days');

  -- Trigger generate_installments() hanya jalan pada UPDATE status → active.
  -- Karena kita insert langsung dengan status 'active'/'paid', generate manual:
  insert into public.installments (loan_id, installment_number, amount, status, paid_at)
  select loan_1, gs, round((5000000 * (1 + 0.015 * 12)) / 12.0, 2),
         case when gs <= 2 then 'paid'::installment_status else 'unpaid'::installment_status end,
         case when gs <= 2 then now() - ((3 - gs) * interval '30 days') else null end
  from generate_series(1, 12) as gs;

  -- Anggota 3 (loan paid) — semua cicilan lunas
  insert into public.installments (loan_id, installment_number, amount, status, paid_at)
  select l.id, gs, round((2000000 * (1 + 0.010 * 6)) / 6.0, 2),
         'paid'::installment_status,
         now() - ((7 - gs) * interval '30 days')
  from public.loans l
  cross join generate_series(1, 6) as gs
  where l.member_id = m3 and l.status = 'paid';
end $$;

-- =========================================================
-- 5. BERSIHKAN FUNGSI SEEDER (opsional, biar rapi)
-- =========================================================
-- drop function if exists public.seed_user(text, text, text, user_role, text, text);

-- =========================================================
-- VERIFIKASI
-- =========================================================
select
  p.full_name,
  p.role,
  u.email,
  m.member_number,
  m.status
from public.profiles p
join auth.users u on u.id = p.id
left join public.members m on m.user_id = p.id
order by p.role, p.full_name;
