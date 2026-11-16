<script setup lang="ts">
const page = settingPage('account')
useHead({ title: page.label })

const { status } = useAdmin()
const { post } = useApi()
const toast = useToast()

const form = reactive({
  user: status.value.admin_user || '',
  oldpwd: '',
  newpwd: '',
  newpwd2: ''
})
const saving = ref(false)

async function onSubmit() {
  if (!form.user.trim()) {
    toast.add({ title: '用户名不能为空', color: 'warning' })
    return
  }
  saving.value = true
  try {
    const res = await post('setAccount', { ...form })
    toast.add({ title: res.msg || '修改成功', color: 'success', icon: 'i-lucide-circle-check' })
    if (res.relogin) {
      status.value = { ...status.value, islogin: 0, admin_user: null }
      await navigateTo('/login')
    }
  } catch (e: any) {
    toast.add({ title: e?.message || '修改失败', color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AdminPage id="set-account" :title="page.label" :icon="page.icon">
    <div class="mx-auto w-full max-w-3xl">
      <UCard>
        <form class="space-y-5" @submit.prevent="onSubmit">
          <UFormField label="用户名" required>
            <UInput v-model="form.user" class="w-full" autocomplete="username" />
          </UFormField>
          <UFormField label="旧密码" help="修改密码时需要填写">
            <UInput v-model="form.oldpwd" type="password" class="w-full" placeholder="请输入当前的管理员密码" autocomplete="current-password" />
          </UFormField>
          <UFormField label="新密码">
            <UInput v-model="form.newpwd" type="password" class="w-full" placeholder="不修改请留空" autocomplete="new-password" />
          </UFormField>
          <UFormField label="重输密码">
            <UInput v-model="form.newpwd2" type="password" class="w-full" placeholder="不修改请留空" autocomplete="new-password" />
          </UFormField>
          <div class="pt-2">
            <UButton type="submit" label="保存修改" icon="i-lucide-save" :loading="saving" />
          </div>
        </form>
      </UCard>
    </div>
  </AdminPage>
</template>
