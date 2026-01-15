function Modals() {
  return {
    state: {},
    payload: {},

    open(name, data = {}) {
      this.state[name] = true;
      this.payload[name] = data;
    },

    close(name) {
      this.state[name] = false;
      delete this.payload[name];
    },

    isOpen(name) {
      return this.state[name] === true;
    },

    get(name) {
      return this.payload[name] || {};
    }
  }
}