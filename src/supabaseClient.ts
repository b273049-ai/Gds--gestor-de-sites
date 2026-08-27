import { createClient } from '@supabase/supabase-js'

const supabaseUrl = 'https://nmaevrantsvvtjwgvzvn.supabase.co'
const supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im5tYWV2cmFudHN2dnRqd2d2enZuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODc2OTk5ODYsImV4cCI6MjEwMzI3NTk4Nn0.eLKwjEP7pTMJQfLoGRntNyF86cO1MbILTAQanqcsLs0'

export const supabase = createClient(supabaseUrl, supabaseAnonKey)
